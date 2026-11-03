<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleActivityLog;
use App\Modules\Hr\Models\VehicleAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fleet Management Service – owned by People & Skills / Resource Management.
 *
 * Handles the complete vehicle lifecycle: assignments, returns, status changes,
 * odometer updates, rental periods, client usage periods, and activity logging.
 *
 * Logistics and Operational Hub must use this service via DI; they must NOT
 * create a duplicate fleet-lifecycle implementation.
 */
class FleetManagementService
{
    /**
     * Assign vehicle to employee / location.
     */
    public function assignVehicle(
        string $vehicleId,
        array $assignmentData,
        ?string $assignedBy = null
    ): VehicleAssignment {
        return DB::transaction(function () use ($vehicleId, $assignmentData, $assignedBy) {
            $vehicle = Vehicle::findOrFail($vehicleId);

            // End current assignment if exists
            $currentAssignment = $vehicle->currentAssignment;
            if ($currentAssignment instanceof VehicleAssignment) {
                $this->endAssignment($currentAssignment->id, $assignedBy);
            }

            // Create new assignment
            $assignment = VehicleAssignment::create([
                'vehicle_id' => $vehicleId,
                'responsible_employee_id' => $assignmentData['responsible_employee_id'] ?? null,
                'custody_type' => $assignmentData['custody_type'],
                'site_id' => $assignmentData['site_id'] ?? null,
                'location_notes' => $assignmentData['location_notes'] ?? null,
                'start_date' => $assignmentData['start_date'] ?? now(),
                'is_current' => true,
                'reason' => $assignmentData['reason'] ?? null,
                'notes' => $assignmentData['notes'] ?? null,
                'odometer_at_start' => $assignmentData['odometer_at_start'] ?? $vehicle->odometer_km,
                'assigned_by' => $assignedBy,
                'created_by' => $assignedBy,
            ]);

            // Update vehicle current assignment fields
            $vehicle->update([
                'current_responsible_employee_id' => $assignmentData['responsible_employee_id'] ?? null,
                'custody_type' => $assignmentData['custody_type'],
                'current_site_id' => $assignmentData['site_id'] ?? null,
                'current_location_notes' => $assignmentData['location_notes'] ?? null,
                'status' => 'in_use',
            ]);

            // Log activity
            $this->logVehicleActivity($vehicleId, [
                'event_type' => 'assignment',
                'event_title' => 'Vehicle Assigned',
                'event_description' => "Vehicle assigned to {$assignmentData['custody_type']}",
                'event_date' => $assignment->start_date,
                'actor_employee_id' => $assignedBy,
                'related_id' => $assignment->id,
                'related_type' => VehicleAssignment::class,
                'odometer_km' => $assignment->odometer_at_start,
            ]);

            return $assignment->fresh();
        });
    }

    /**
     * End vehicle assignment / return.
     */
    public function endAssignment(
        string $assignmentId,
        ?string $returnedBy = null,
        ?array $returnData = null
    ): VehicleAssignment {
        return DB::transaction(function () use ($assignmentId, $returnedBy, $returnData) {
            $assignment = VehicleAssignment::findOrFail($assignmentId);
            $vehicle = $assignment->vehicle;
            $vehicleOdometer = $vehicle instanceof Vehicle ? $vehicle->odometer_km : null;
            $vehicleId = $vehicle instanceof Vehicle ? (string) $vehicle->id : (string) $assignment->vehicle_id;

            $assignment->update([
                'end_date' => $returnData['end_date'] ?? now(),
                'is_current' => false,
                'odometer_at_end' => $returnData['odometer_at_end'] ?? $vehicleOdometer,
                'returned_by' => $returnedBy,
                'updated_by' => $returnedBy,
            ]);

            $vehicleUpdates = [
                'status' => $returnData['vehicle_status'] ?? 'available',
                'current_responsible_employee_id' => null,
                'custody_type' => null,
            ];

            $currentKm = $vehicle instanceof Vehicle ? ($vehicle->odometer_km ?? 0) : 0;
            if (! empty($returnData['odometer_at_end']) && $returnData['odometer_at_end'] > $currentKm) {
                $vehicleUpdates['odometer_km'] = $returnData['odometer_at_end'];
                $vehicleUpdates['odometer_last_updated'] = now();
            }

            if ($vehicle instanceof Vehicle) {
                $vehicle->update($vehicleUpdates);
            }

            // Log activity
            $this->logVehicleActivity($vehicleId, [
                'event_type' => 'reassignment',
                'event_title' => 'Vehicle Returned',
                'event_description' => "Vehicle returned from {$assignment->custody_type}",
                'event_date' => $assignment->end_date,
                'actor_employee_id' => $returnedBy,
                'related_id' => $assignment->id,
                'related_type' => VehicleAssignment::class,
                'odometer_km' => $assignment->odometer_at_end,
            ]);

            return $assignment->fresh();
        });
    }

    /**
     * Update vehicle odometer reading.
     */
    public function updateOdometer(
        string $vehicleId,
        int $odometerKm,
        ?string $updatedBy = null
    ): Vehicle {
        $vehicle = Vehicle::findOrFail($vehicleId);
        $oldOdometer = $vehicle->odometer_km;

        $vehicle->update([
            'odometer_km' => $odometerKm,
            'odometer_last_updated' => now(),
            'updated_by' => $updatedBy,
        ]);

        // Log activity
        $this->logVehicleActivity($vehicleId, [
            'event_type' => 'odometer_update',
            'event_title' => 'Odometer Updated',
            'event_description' => "Odometer updated from {$oldOdometer} km to {$odometerKm} km",
            'event_date' => now(),
            'old_value' => (string) $oldOdometer,
            'new_value' => (string) $odometerKm,
            'actor_employee_id' => $updatedBy,
            'odometer_km' => $odometerKm,
        ]);

        return $vehicle->fresh();
    }

    /**
     * Change vehicle operational status.
     */
    public function updateStatus(
        string $vehicleId,
        string $newStatus,
        ?string $reason = null,
        ?string $updatedBy = null
    ): Vehicle {
        $vehicle = Vehicle::findOrFail($vehicleId);
        $oldStatus = $vehicle->status;

        $vehicle->update([
            'status' => $newStatus,
            'updated_by' => $updatedBy,
        ]);

        // Log activity
        $this->logVehicleActivity($vehicleId, [
            'event_type' => 'status_change',
            'event_title' => 'Status Changed',
            'event_description' => $reason ?? "Status changed from {$oldStatus} to {$newStatus}",
            'event_date' => now(),
            'old_value' => $oldStatus,
            'new_value' => $newStatus,
            'actor_employee_id' => $updatedBy,
        ]);

        return $vehicle->fresh();
    }

    /**
     * Start a rental vehicle usage period.
     */
    public function startRentalUsage(
        string $vehicleId,
        array $rentalData,
        ?string $startedBy = null
    ): Vehicle {
        return DB::transaction(function () use ($vehicleId, $rentalData, $startedBy) {
            $vehicle = Vehicle::findOrFail($vehicleId);

            $vehicle->update([
                'ownership_type' => 'rental',
                'rental_provider_id' => $rentalData['rental_provider_id'],
                'rental_start_date' => $rentalData['rental_start_date'] ?? now(),
                'rental_end_date' => $rentalData['rental_end_date'],
                'rental_contract_reference' => $rentalData['rental_contract_reference'] ?? null,
                'rental_daily_rate' => $rentalData['rental_daily_rate'] ?? null,
                'rental_currency' => $rentalData['rental_currency'] ?? 'TND',
                'rental_status' => 'active',
                'status' => 'rental_active',
                'updated_by' => $startedBy,
            ]);

            // Log activity
            $this->logVehicleActivity($vehicleId, [
                'event_type' => 'rental_start',
                'event_title' => 'Rental Started',
                'event_description' => "Rental period started until {$rentalData['rental_end_date']}",
                'event_date' => $vehicle->rental_start_date,
                'actor_employee_id' => $startedBy,
                'metadata' => $rentalData,
            ]);

            return $vehicle->fresh();
        });
    }

    /**
     * End a rental vehicle usage period.
     */
    public function endRentalUsage(
        string $vehicleId,
        ?array $returnData = null,
        ?string $endedBy = null
    ): Vehicle {
        return DB::transaction(function () use ($vehicleId, $returnData, $endedBy) {
            $vehicle = Vehicle::findOrFail($vehicleId);

            $vehicle->update([
                'rental_return_date' => $returnData['rental_return_date'] ?? now(),
                'rental_status' => 'returned',
                'status' => 'available',
                'updated_by' => $endedBy,
            ]);

            // Log activity
            $this->logVehicleActivity($vehicleId, [
                'event_type' => 'rental_end',
                'event_title' => 'Rental Ended',
                'event_description' => 'Rental vehicle returned',
                'event_date' => $vehicle->rental_return_date,
                'actor_employee_id' => $endedBy,
                'metadata' => $returnData,
            ]);

            return $vehicle->fresh();
        });
    }

    /**
     * Start a client-supplied vehicle usage period.
     */
    public function startClientVehicleUsage(
        string $vehicleId,
        array $clientData,
        ?string $startedBy = null
    ): Vehicle {
        return DB::transaction(function () use ($vehicleId, $clientData, $startedBy) {
            $vehicle = Vehicle::findOrFail($vehicleId);

            $vehicle->update([
                'ownership_type' => 'client',
                'client_id' => $clientData['client_id'],
                'client_supplies_driver' => $clientData['client_supplies_driver'] ?? false,
                'client_usage_start' => $clientData['client_usage_start'] ?? now(),
                'client_usage_end' => $clientData['client_usage_end'] ?? null,
                'client_vehicle_status' => 'active',
                'status' => 'client_use',
                'updated_by' => $startedBy,
            ]);

            // Log activity
            $this->logVehicleActivity($vehicleId, [
                'event_type' => 'client_use_start',
                'event_title' => 'Client Vehicle Usage Started',
                'event_description' => 'Client vehicle usage period started',
                'event_date' => $vehicle->client_usage_start,
                'actor_employee_id' => $startedBy,
                'metadata' => $clientData,
            ]);

            return $vehicle->fresh();
        });
    }

    /**
     * End a client-supplied vehicle usage period.
     */
    public function endClientVehicleUsage(
        string $vehicleId,
        ?string $endedBy = null
    ): Vehicle {
        return DB::transaction(function () use ($vehicleId, $endedBy) {
            $vehicle = Vehicle::findOrFail($vehicleId);

            $vehicle->update([
                'client_vehicle_status' => 'returned',
                'status' => 'available',
                'updated_by' => $endedBy,
            ]);

            // Log activity
            $this->logVehicleActivity($vehicleId, [
                'event_type' => 'client_use_end',
                'event_title' => 'Client Vehicle Usage Ended',
                'event_description' => 'Client vehicle returned',
                'event_date' => now(),
                'actor_employee_id' => $endedBy,
            ]);

            return $vehicle->fresh();
        });
    }

    /**
     * Append a fleet event to the vehicle activity log.
     */
    public function logVehicleActivity(string $vehicleId, array $activityData): VehicleActivityLog
    {
        return VehicleActivityLog::create([
            'vehicle_id' => $vehicleId,
            'event_type' => $activityData['event_type'],
            'event_title' => $activityData['event_title'],
            'event_description' => $activityData['event_description'] ?? null,
            'event_date' => $activityData['event_date'] ?? now(),
            'old_value' => $activityData['old_value'] ?? null,
            'new_value' => $activityData['new_value'] ?? null,
            'metadata' => $activityData['metadata'] ?? null,
            'related_id' => $activityData['related_id'] ?? null,
            'related_type' => $activityData['related_type'] ?? null,
            'actor_employee_id' => $activityData['actor_employee_id'] ?? null,
            'actor_name' => $activityData['actor_name'] ?? null,
            'location' => $activityData['location'] ?? null,
            'odometer_km' => $activityData['odometer_km'] ?? null,
            'created_by' => $activityData['actor_employee_id'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * Return vehicles that require maintenance within the given window.
     */
    public function getVehiclesRequiringMaintenance(int $daysAhead = 7): \Illuminate\Database\Eloquent\Collection
    {
        return Vehicle::active()
            ->where(function ($query) use ($daysAhead) {
                $query->where('next_service_date', '<=', now()->addDays($daysAhead))
                    ->orWhere('next_inspection_date', '<=', now()->addDays($daysAhead));
            })
            ->with(['currentAssignment.responsibleEmployee'])
            ->get();
    }

    /**
     * Return vehicles with compliance documents expiring within the given window.
     */
    public function getVehiclesWithExpiringDocuments(int $daysAhead = 30): \Illuminate\Database\Eloquent\Collection
    {
        return Vehicle::active()
            ->where(function ($query) use ($daysAhead) {
                $threshold = now()->addDays($daysAhead);
                $query->where('insurance_expiry_date', '<=', $threshold)
                    ->orWhere('registration_expiry_date', '<=', $threshold);
            })
            ->get();
    }

    /**
     * Calculate the utilization rate for a vehicle in a given period.
     */
    public function calculateVehicleUtilization(string $vehicleId, Carbon $startDate, Carbon $endDate): array
    {
        $vehicle = Vehicle::findOrFail($vehicleId);
        $totalDays = $startDate->diffInDays($endDate);

        $assignments = VehicleAssignment::byVehicle($vehicleId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where(function ($q2) use ($endDate) {
                                $q2->where('end_date', '>=', $endDate)
                                    ->orWhereNull('end_date');
                            });
                    });
            })
            ->get();

        $usedDays = 0;
        foreach ($assignments as $assignment) {
            $startCarbon = $assignment->start_date ? Carbon::parse($assignment->start_date) : $startDate;
            $endCarbon = $assignment->end_date ? Carbon::parse($assignment->end_date) : $endDate;
            $assignStart = $startCarbon->max($startDate);
            $assignEnd = $endCarbon->min($endDate);
            $usedDays += (int) $assignStart->diffInDays($assignEnd);
        }

        return [
            'vehicle_id' => $vehicleId,
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'total_days' => $totalDays,
            'used_days' => $usedDays,
            'available_days' => $totalDays - $usedDays,
            'utilization_rate' => $totalDays > 0 ? round(($usedDays / $totalDays) * 100, 2) : 0,
        ];
    }
}
