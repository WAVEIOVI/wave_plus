<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\Employee;
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
     * Assign vehicle to employee / location with proper locking and validation.
     */
    public function assignVehicle(
        string $vehicleId,
        array $assignmentData,
        ?string $assignedBy = null
    ): VehicleAssignment {
        return DB::transaction(function () use ($vehicleId, $assignmentData, $assignedBy) {
            // Pessimistic locking to prevent race conditions
            $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicleId);

            // Validate vehicle is not in a non-assignable state
            if (in_array($vehicle->status, ['retired', 'sold'])) {
                throw new \InvalidArgumentException("Vehicle {$vehicle->fleet_code} cannot be assigned (status: {$vehicle->status})");
            }

            // End current assignment if exists (with lock)
            $currentAssignment = VehicleAssignment::lockForUpdate()
                ->where('vehicle_id', $vehicleId)
                ->where('is_current', true)
                ->first();

            if ($currentAssignment instanceof VehicleAssignment) {
                $this->endAssignmentInternal($currentAssignment, $assignedBy);
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

            // Update helper column for MySQL unique constraint
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                $assignment->update(['unique_current_key' => "current_{$vehicleId}"]);
            }

            // Validate and update odometer if provided
            $odometerAtStart = $assignmentData['odometer_at_start'] ?? $vehicle->odometer_km;
            if ($odometerAtStart !== null && $odometerAtStart < ($vehicle->odometer_km ?? 0)) {
                if (! $vehicle->allow_odometer_decrease) {
                    throw new \InvalidArgumentException(
                        "Odometer cannot decrease from {$vehicle->odometer_km} km to {$odometerAtStart} km. ".
                        'Use authorized correction mechanism if this is intentional.'
                    );
                }
            }

            // Update vehicle current assignment fields
            $vehicle->update([
                'current_responsible_employee_id' => $assignmentData['responsible_employee_id'] ?? null,
                'custody_type' => $assignmentData['custody_type'],
                'current_site_id' => $assignmentData['site_id'] ?? null,
                'current_location_notes' => $assignmentData['location_notes'] ?? null,
                'status' => 'in_use',
                'has_operational_history' => true,
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
     * End vehicle assignment / return with complete cleanup.
     */
    public function endAssignment(
        string $assignmentId,
        ?string $returnedBy = null,
        ?array $returnData = null
    ): VehicleAssignment {
        return DB::transaction(function () use ($assignmentId, $returnedBy, $returnData) {
            $assignment = VehicleAssignment::lockForUpdate()->findOrFail($assignmentId);
            
            return $this->endAssignmentInternal($assignment, $returnedBy, $returnData);
        });
    }

    /**
     * Internal method to end assignment (can be called within existing transaction).
     */
    private function endAssignmentInternal(
        VehicleAssignment $assignment,
        ?string $returnedBy = null,
        ?array $returnData = null
    ): VehicleAssignment {
        $vehicle = Vehicle::lockForUpdate()->findOrFail($assignment->vehicle_id);
        $vehicleOdometer = $vehicle->odometer_km ?? 0;
        $vehicleId = (string) $vehicle->id;

        $endDate = Carbon::parse($returnData['end_date'] ?? now());
        $odometerAtEnd = $returnData['odometer_at_end'] ?? $vehicleOdometer;

        // Validate end date is not before start date (same day is allowed)
        $startDate = Carbon::parse($assignment->start_date);
        if ($endDate->isBefore($startDate->startOfDay())) {
            throw new \InvalidArgumentException(
                'Assignment end date cannot be before start date. '.
                "Start: {$startDate->toDateString()}, End: {$endDate->toDateString()}"
            );
        }

        // Validate odometer at end >= odometer at start
        if ($odometerAtEnd !== null && $assignment->odometer_at_start !== null) {
            if ($odometerAtEnd < $assignment->odometer_at_start && ! $vehicle->allow_odometer_decrease) {
                throw new \InvalidArgumentException(
                    "Odometer at return ({$odometerAtEnd} km) cannot be less than odometer at assignment ({$assignment->odometer_at_start} km)"
                );
            }
        }

        // Update assignment
        $assignment->update([
            'end_date' => $endDate,
            'is_current' => false,
            'odometer_at_end' => $odometerAtEnd,
            'returned_by' => $returnedBy,
            'updated_by' => $returnedBy,
        ]);

        // Clear MySQL helper column if it exists
        if (DB::connection()->getDriverName() === 'mysql') {
            $assignment->update(['unique_current_key' => null]);
        }

        // Determine return destination custody
        $returnDestinationSite = $returnData['return_to_site_id'] ?? null;
        $returnCustodyType = $returnData['return_custody_type'] ?? null;

        $vehicleUpdates = [
            'status' => $returnData['vehicle_status'] ?? 'available',
            'current_responsible_employee_id' => null,
            'custody_type' => $returnCustodyType,
            'current_site_id' => $returnDestinationSite,
            'current_location_notes' => $returnData['location_notes'] ?? null,
        ];

        // Update odometer if increased
        if ($odometerAtEnd !== null && $odometerAtEnd > $vehicleOdometer) {
            $vehicleUpdates['odometer_km'] = (int) $odometerAtEnd;
            $vehicleUpdates['odometer_last_updated'] = $endDate;

            // Append to odometer history
            $this->recordOdometerHistory($vehicle, $vehicleOdometer, (int) $odometerAtEnd, 'assignment_return', $returnedBy);
        }

        $vehicle->update($vehicleUpdates);

        // Log activity
        $this->logVehicleActivity($vehicleId, [
            'event_type' => 'assignment_end',
            'event_title' => 'Vehicle Returned',
            'event_description' => "Vehicle returned from {$assignment->custody_type}".
                ($returnDestinationSite ? " to site" : ''),
            'event_date' => $assignment->end_date,
            'actor_employee_id' => $returnedBy,
            'related_id' => $assignment->id,
            'related_type' => VehicleAssignment::class,
            'odometer_km' => $assignment->odometer_at_end,
        ]);

        return $assignment->fresh();
    }

    /**
     * Update vehicle odometer reading with decrease protection.
     */
    public function updateOdometer(
        string $vehicleId,
        int $odometerKm,
        ?string $updatedBy = null,
        bool $allowDecrease = false
    ): Vehicle {
        return DB::transaction(function () use ($vehicleId, $odometerKm, $updatedBy, $allowDecrease) {
            $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicleId);
            $oldOdometer = $vehicle->odometer_km ?? 0;

            // Validate odometer cannot decrease unless explicitly allowed
            if ($odometerKm < $oldOdometer) {
                if (! $allowDecrease && ! $vehicle->allow_odometer_decrease) {
                    throw new \InvalidArgumentException(
                        "Odometer reading cannot decrease from {$oldOdometer} km to {$odometerKm} km. ".
                        'Contact fleet administrator for authorized correction.'
                    );
                }

                // Log authorized decrease
                $this->logVehicleActivity($vehicleId, [
                    'event_type' => 'odometer_correction',
                    'event_title' => '⚠️ Authorized Odometer Correction (Decrease)',
                    'event_description' => "Odometer corrected from {$oldOdometer} km to {$odometerKm} km (authorized decrease)",
                    'event_date' => now(),
                    'old_value' => (string) $oldOdometer,
                    'new_value' => (string) $odometerKm,
                    'actor_employee_id' => $updatedBy,
                    'odometer_km' => $odometerKm,
                    'metadata' => ['authorized_decrease' => true, 'reason' => 'Manual correction'],
                ]);
            }

            $vehicle->update([
                'odometer_km' => $odometerKm,
                'odometer_last_updated' => now(),
                'updated_by' => $updatedBy,
            ]);

            // Record in history
            $this->recordOdometerHistory($vehicle, $oldOdometer, $odometerKm, 'manual_update', $updatedBy);

            // Log regular update
            if ($odometerKm >= $oldOdometer) {
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
            }

            return $vehicle->fresh();
        });
    }

    /**
     * Record odometer change in audit history.
     */
    private function recordOdometerHistory(
        Vehicle $vehicle,
        int $oldValue,
        int $newValue,
        string $changeType,
        ?string $changedBy
    ): void {
        $history = $vehicle->odometer_history ?? [];
        $history[] = [
            'timestamp' => now()->toIso8601String(),
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'change_type' => $changeType,
            'changed_by' => $changedBy,
        ];

        // Keep last 100 entries
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        $vehicle->update(['odometer_history' => $history]);
    }

    /**
     * Authorized odometer correction for data quality fixes.
     * Requires explicit authorization and audits the correction.
     */
    public function correctOdometer(
        string $vehicleId,
        int $correctedOdometer,
        string $correctionReason,
        ?string $authorizedBy = null
    ): Vehicle {
        return DB::transaction(function () use ($vehicleId, $correctedOdometer, $correctionReason, $authorizedBy) {
            $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicleId);
            $oldOdometer = $vehicle->odometer_km ?? 0;

            $vehicle->update([
                'odometer_km' => $correctedOdometer,
                'odometer_last_updated' => now(),
                'updated_by' => $authorizedBy,
            ]);

            // Record in history with correction flag
            $this->recordOdometerHistory($vehicle, $oldOdometer, $correctedOdometer, 'authorized_correction', $authorizedBy);

            // Log the correction with full details
            $this->logVehicleActivity($vehicleId, [
                'event_type' => 'odometer_correction',
                'event_title' => '✓ Authorized Odometer Correction',
                'event_description' => "Odometer corrected: {$oldOdometer} km → {$correctedOdometer} km. Reason: {$correctionReason}",
                'event_date' => now(),
                'old_value' => (string) $oldOdometer,
                'new_value' => (string) $correctedOdometer,
                'actor_employee_id' => $authorizedBy,
                'odometer_km' => $correctedOdometer,
                'metadata' => [
                    'correction_reason' => $correctionReason,
                    'authorized_by' => $authorizedBy,
                    'is_decrease' => $correctedOdometer < $oldOdometer,
                ],
            ]);

            return $vehicle->fresh();
        });
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

    /**
     * Update vehicle GPS telemetry and location history.
     */
    public function updateTelemetry(
        string $vehicleId,
        array $telemetryData,
        ?string $updatedBy = null
    ): Vehicle {
        return DB::transaction(function () use ($vehicleId, $telemetryData, $updatedBy) {
            $vehicle = Vehicle::findOrFail($vehicleId);

            $updates = [
                'last_gps_latitude' => $telemetryData['latitude'] ?? $telemetryData['last_gps_latitude'] ?? null,
                'last_gps_longitude' => $telemetryData['longitude'] ?? $telemetryData['last_gps_longitude'] ?? null,
                'last_gps_timestamp' => $telemetryData['timestamp'] ?? $telemetryData['last_gps_timestamp'] ?? now(),
                'last_location_address' => $telemetryData['address'] ?? $telemetryData['last_location_address'] ?? null,
                'last_speed_kmh' => $telemetryData['speed_kmh'] ?? $telemetryData['last_speed_kmh'] ?? null,
                'updated_by' => $updatedBy,
            ];

            if (! empty($telemetryData['odometer_km']) && $telemetryData['odometer_km'] > ($vehicle->odometer_km ?? 0)) {
                $updates['odometer_km'] = (int) $telemetryData['odometer_km'];
                $updates['odometer_last_updated'] = now();
            }

            if (! empty($telemetryData['tracker_device_id'])) {
                $updates['tracker_device_id'] = $telemetryData['tracker_device_id'];
            }
            if (! empty($telemetryData['tracker_provider'])) {
                $updates['tracker_provider'] = $telemetryData['tracker_provider'];
            }

            $vehicle->update($updates);

            $this->logVehicleActivity($vehicleId, [
                'event_type' => 'location_change',
                'event_title' => 'GPS Telemetry & Location Updated',
                'event_description' => 'Location: '.($updates['last_location_address'] ?? "{$updates['last_gps_latitude']}, {$updates['last_gps_longitude']}"),
                'event_date' => $updates['last_gps_timestamp'],
                'actor_employee_id' => $updatedBy,
                'location' => $updates['last_location_address'] ?? "{$updates['last_gps_latitude']}, {$updates['last_gps_longitude']}",
                'odometer_km' => $vehicle->odometer_km,
                'metadata' => [
                    'latitude' => $updates['last_gps_latitude'],
                    'longitude' => $updates['last_gps_longitude'],
                    'speed_kmh' => $updates['last_speed_kmh'],
                ],
            ]);

            return $vehicle->fresh();
        });
    }

    /**
     * Archive a vehicle (soft delete with explicit archival tracking).
     */
    public function archiveVehicle(
        string $vehicleId,
        string $archivalReason,
        ?string $archivedBy = null
    ): Vehicle {
        return DB::transaction(function () use ($vehicleId, $archivalReason, $archivedBy) {
            $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicleId);

            // End any current assignment
            $currentAssignment = VehicleAssignment::lockForUpdate()
                ->where('vehicle_id', $vehicleId)
                ->where('is_current', true)
                ->first();

            if ($currentAssignment instanceof VehicleAssignment) {
                $this->endAssignmentInternal($currentAssignment, $archivedBy, [
                    'vehicle_status' => 'retired',
                ]);
            }

            $vehicle->update([
                'status' => 'retired',
                'archived_at' => now(),
                'archived_by' => $archivedBy,
            ]);

            $this->logVehicleActivity($vehicleId, [
                'event_type' => 'archived',
                'event_title' => 'Vehicle Archived',
                'event_description' => "Vehicle archived: {$archivalReason}",
                'event_date' => now(),
                'actor_employee_id' => $archivedBy,
                'metadata' => ['reason' => $archivalReason],
            ]);

            return $vehicle->fresh();
        });
    }

    /**
     * Check if vehicle can be safely deleted.
     */
    public function canDeleteVehicle(string $vehicleId): array
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        $blockers = [];

        // Check operational history
        if ($vehicle->has_operational_history) {
            $blockers[] = 'Vehicle has operational history (assignments, maintenance, fuel records, etc.)';
        }

        // Check active assignments
        $activeAssignments = VehicleAssignment::where('vehicle_id', $vehicleId)
            ->where('is_current', true)
            ->count();
        if ($activeAssignments > 0) {
            $blockers[] = 'Vehicle has active assignment';
        }

        // Check maintenance records
        $maintenanceCount = $vehicle->maintenanceRecords()->count();
        if ($maintenanceCount > 0) {
            $blockers[] = "Vehicle has {$maintenanceCount} maintenance record(s)";
        }

        // Check fuel records
        $fuelCount = $vehicle->fuelRecords()->count();
        if ($fuelCount > 0) {
            $blockers[] = "Vehicle has {$fuelCount} fuel record(s)";
        }

        // Check checklists
        $checklistCount = $vehicle->checklists()->count();
        if ($checklistCount > 0) {
            $blockers[] = "Vehicle has {$checklistCount} inspection checklist(s)";
        }

        // Check documents
        $documentCount = $vehicle->documents()->count();
        if ($documentCount > 0) {
            $blockers[] = "Vehicle has {$documentCount} document(s)";
        }

        return [
            'can_delete' => empty($blockers),
            'blockers' => $blockers,
            'recommendation' => empty($blockers)
                ? 'Vehicle can be safely deleted'
                : 'Consider archiving instead of deleting to preserve historical records',
        ];
    }

    /**
     * Safe vehicle deletion with dependency check.
     * Throws exception if vehicle has operational dependencies.
     */
    public function deleteVehicle(string $vehicleId, bool $force = false): bool
    {
        return DB::transaction(function () use ($vehicleId, $force) {
            $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicleId);

            if (! $force) {
                $safetyCheck = $this->canDeleteVehicle($vehicleId);
                if (! $safetyCheck['can_delete']) {
                    throw new \RuntimeException(
                        'Vehicle cannot be deleted: '.implode('; ', $safetyCheck['blockers']).'. '.
                        $safetyCheck['recommendation']
                    );
                }
            }

            // Log deletion before removing
            $this->logVehicleActivity($vehicleId, [
                'event_type' => 'deleted',
                'event_title' => '⚠️ Vehicle Deleted',
                'event_description' => "Vehicle {$vehicle->fleet_code} permanently deleted".($force ? ' (forced)' : ''),
                'event_date' => now(),
                'metadata' => [
                    'force' => $force,
                    'fleet_code' => $vehicle->fleet_code,
                    'plate_number' => $vehicle->plate_number,
                ],
            ]);

            $vehicle->delete();

            return true;
        });
    }

    /**
     * Check if employee is authorized to drive a specific vehicle.
     * Default driver designation does NOT grant driving authorization.
     * 
     * @param string $userId The user_id (since controller validates against users table)
     * @param string $vehicleId The vehicle ID
     */
    public function isAuthorizedDriver(string $userId, string $vehicleId): array
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        // Find employee by user_id
        $employee = Employee::where('user_id', $userId)->first();
        
        if (! $employee) {
            return [
                'authorized' => false,
                'reason' => 'Employee record not found for this user',
            ];
        }

        // Check if employee has approved driver authorization
        $approvedDriver = ApprovedDriver::active()
            ->where('employee_id', $employee->id)
            ->first();

        if (! $approvedDriver) {
            return [
                'authorized' => false,
                'reason' => 'Employee does not have an active Approved Driver authorization',
            ];
        }

        // Check vehicle type permission
        if (! empty($approvedDriver->permitted_vehicle_types)) {
            if (! in_array($vehicle->type, $approvedDriver->permitted_vehicle_types)) {
                return [
                    'authorized' => false,
                    'reason' => "Driver not authorized for vehicle type: {$vehicle->type}",
                    'permitted_types' => $approvedDriver->permitted_vehicle_types,
                ];
            }
        }

        // Check vehicle class permission
        if (! empty($approvedDriver->permitted_vehicle_classes) && ! empty($vehicle->vehicle_class)) {
            if (! in_array($vehicle->vehicle_class, $approvedDriver->permitted_vehicle_classes)) {
                return [
                    'authorized' => false,
                    'reason' => "Driver not authorized for vehicle class: {$vehicle->vehicle_class}",
                    'permitted_classes' => $approvedDriver->permitted_vehicle_classes,
                ];
            }
        }

        // Check ownership-specific permissions
        if ($vehicle->ownership_type === 'rental' && ! $approvedDriver->can_drive_rental) {
            return [
                'authorized' => false,
                'reason' => 'Driver not authorized to drive rental vehicles',
            ];
        }

        if ($vehicle->ownership_type === 'client' && ! $approvedDriver->can_drive_client_vehicles) {
            return [
                'authorized' => false,
                'reason' => 'Driver not authorized to drive client-supplied vehicles',
            ];
        }

        return [
            'authorized' => true,
            'driver_authorization' => $approvedDriver->driver_authorization_number,
            'driver_level' => $approvedDriver->driver_level,
            'license_expiry' => $approvedDriver->license_expiry_date?->format('Y-m-d'),
        ];
    }
}
