<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleDocument;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Fleet Alerts Service
 *
 * Detects upcoming and overdue alerts for vehicles and drivers:
 * - Maintenance (date-based and odometer-based)
 * - Insurance expiry (from vehicle_documents)
 * - Registration expiry (from vehicle_documents)
 * - Driver license expiry
 * - Driver authorization expiry
 * - Driver authorization review dates
 * - Suspended/revoked driver status
 */
class FleetAlertsService
{
    /**
     * Get all active fleet alerts aggregated by category.
     *
     * @param  int  $daysAhead  How many days ahead to check for upcoming alerts (default: 30)
     * @return array Alerts grouped by category with counts and details
     */
    public function getAllAlerts(int $daysAhead = 30): array
    {
        $today = Carbon::today();
        $futureDate = $today->copy()->addDays($daysAhead);

        $vehicleAlerts = $this->getVehicleAlerts($today, $futureDate);
        $driverAlerts = $this->getDriverAlerts($today, $futureDate);

        $totalOverdue = $vehicleAlerts['overdue_count'] + $driverAlerts['overdue_count'];
        $totalUpcoming = $vehicleAlerts['upcoming_count'] + $driverAlerts['upcoming_count'];

        return [
            'summary' => [
                'total_alerts' => $totalOverdue + $totalUpcoming,
                'overdue' => $totalOverdue,
                'upcoming' => $totalUpcoming,
                'critical' => $vehicleAlerts['critical_count'] + $driverAlerts['critical_count'],
            ],
            'vehicle_alerts' => $vehicleAlerts,
            'driver_alerts' => $driverAlerts,
        ];
    }

    /**
     * Get all vehicle-related alerts.
     *
     * @param  Carbon  $today  Reference date (usually today)
     * @param  Carbon  $futureDate  Alerts due before this date are "upcoming"
     * @return array Vehicle alerts with overdue/upcoming items
     */
    public function getVehicleAlerts(Carbon $today, Carbon $futureDate): array
    {
        $maintenance = $this->getMaintenanceAlerts($today, $futureDate);
        $insurance = $this->getInsuranceAlerts($today, $futureDate);
        $registration = $this->getRegistrationAlerts($today, $futureDate);
        $documents = $this->getVehicleDocumentAlerts($today, $futureDate);

        $overdueCount = $maintenance['overdue']->count()
            + $insurance['overdue']->count()
            + $registration['overdue']->count()
            + $documents['overdue']->count();

        $upcomingCount = $maintenance['upcoming']->count()
            + $insurance['upcoming']->count()
            + $registration['upcoming']->count()
            + $documents['upcoming']->count();

        // Critical: overdue maintenance or expired insurance/registration
        $criticalCount = $maintenance['overdue']->count()
            + $insurance['overdue']->count()
            + $registration['overdue']->count();

        return [
            'overdue_count' => $overdueCount,
            'upcoming_count' => $upcomingCount,
            'critical_count' => $criticalCount,
            'maintenance' => $maintenance,
            'insurance' => $insurance,
            'registration' => $registration,
            'other_documents' => $documents,
        ];
    }

    /**
     * Get all driver-related alerts.
     *
     * @param  Carbon  $today  Reference date
     * @param  Carbon  $futureDate  Upcoming threshold
     * @return array Driver alerts with overdue/upcoming items
     */
    public function getDriverAlerts(Carbon $today, Carbon $futureDate): array
    {
        $licenses = $this->getDriverLicenseAlerts($today, $futureDate);
        $authorizations = $this->getDriverAuthorizationAlerts($today, $futureDate);
        $reviews = $this->getDriverReviewAlerts($today, $futureDate);
        $suspended = $this->getSuspendedDriverAlerts();
        $revoked = $this->getRevokedDriverAlerts();

        $overdueCount = $licenses['overdue']->count()
            + $authorizations['overdue']->count()
            + $reviews['overdue']->count();

        $upcomingCount = $licenses['upcoming']->count()
            + $authorizations['upcoming']->count()
            + $reviews['upcoming']->count();

        // Critical: expired licenses, suspended/revoked drivers
        $criticalCount = $licenses['overdue']->count()
            + $suspended->count()
            + $revoked->count();

        return [
            'overdue_count' => $overdueCount,
            'upcoming_count' => $upcomingCount,
            'critical_count' => $criticalCount,
            'licenses' => $licenses,
            'authorizations' => $authorizations,
            'reviews' => $reviews,
            'suspended' => $suspended,
            'revoked' => $revoked,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  Vehicle Alerts — Maintenance
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get maintenance alerts based on date AND odometer thresholds.
     *
     * @param  Carbon  $today  Reference date
     * @param  Carbon  $futureDate  Upcoming threshold
     * @return array Overdue and upcoming maintenance alerts
     */
    public function getMaintenanceAlerts(Carbon $today, Carbon $futureDate): array
    {
        // Date-based: next_service_date or next_inspection_date on the vehicle
        $dateBasedOverdue = Vehicle::where(function ($q) use ($today) {
            $q->where(function ($inner) use ($today) {
                $inner->whereNotNull('next_service_date')
                    ->where('next_service_date', '<', $today);
            })->orWhere(function ($inner) use ($today) {
                $inner->whereNotNull('next_inspection_date')
                    ->where('next_inspection_date', '<', $today);
            });
        })
            ->whereNotIn('status', ['retired', 'sold'])
            ->with('currentAssignment')
            ->get()
            ->map(function (Vehicle $vehicle) use ($today) {
                $daysOverdue = 0;
                $reason = [];

                if ($vehicle->next_service_date && $vehicle->next_service_date->lt($today)) {
                    $daysOverdue = max($daysOverdue, $today->diffInDays($vehicle->next_service_date));
                    $reason[] = "Service overdue ({$vehicle->next_service_date->format('Y-m-d')})";
                }

                if ($vehicle->next_inspection_date && $vehicle->next_inspection_date->lt($today)) {
                    $daysOverdue = max($daysOverdue, $today->diffInDays($vehicle->next_inspection_date));
                    $reason[] = "Inspection overdue ({$vehicle->next_inspection_date->format('Y-m-d')})";
                }

                return [
                    'vehicle_id' => $vehicle->id,
                    'fleet_code' => $vehicle->fleet_code,
                    'plate_number' => $vehicle->plate_number,
                    'label' => $vehicle->label,
                    'type' => $vehicle->type,
                    'status' => $vehicle->status,
                    'next_service_date' => $vehicle->next_service_date?->format('Y-m-d'),
                    'next_inspection_date' => $vehicle->next_inspection_date?->format('Y-m-d'),
                    'current_odometer_km' => $vehicle->odometer_km,
                    'alert_type' => 'maintenance_overdue',
                    'days_overdue' => $daysOverdue,
                    'reason' => implode('; ', $reason),
                ];
            });

        $dateBasedUpcoming = Vehicle::where(function ($q) use ($today, $futureDate) {
            $q->where(function ($inner) use ($today, $futureDate) {
                $inner->whereNotNull('next_service_date')
                    ->where('next_service_date', '>=', $today)
                    ->where('next_service_date', '<=', $futureDate);
            })->orWhere(function ($inner) use ($today, $futureDate) {
                $inner->whereNotNull('next_inspection_date')
                    ->where('next_inspection_date', '>=', $today)
                    ->where('next_inspection_date', '<=', $futureDate);
            });
        })
            ->whereNotIn('status', ['retired', 'sold'])
            ->with('currentAssignment')
            ->get()
            ->map(function (Vehicle $vehicle) use ($today) {
                $daysUntilDue = PHP_INT_MAX;
                $reason = [];

                if ($vehicle->next_service_date && $vehicle->next_service_date->gte($today)) {
                    $daysUntilDue = min($daysUntilDue, $today->diffInDays($vehicle->next_service_date, false));
                    $reason[] = "Service due ({$vehicle->next_service_date->format('Y-m-d')})";
                }

                if ($vehicle->next_inspection_date && $vehicle->next_inspection_date->gte($today)) {
                    $daysUntilDue = min($daysUntilDue, $today->diffInDays($vehicle->next_inspection_date, false));
                    $reason[] = "Inspection due ({$vehicle->next_inspection_date->format('Y-m-d')})";
                }

                return [
                    'vehicle_id' => $vehicle->id,
                    'fleet_code' => $vehicle->fleet_code,
                    'plate_number' => $vehicle->plate_number,
                    'label' => $vehicle->label,
                    'type' => $vehicle->type,
                    'status' => $vehicle->status,
                    'next_service_date' => $vehicle->next_service_date?->format('Y-m-d'),
                    'next_inspection_date' => $vehicle->next_inspection_date?->format('Y-m-d'),
                    'current_odometer_km' => $vehicle->odometer_km,
                    'alert_type' => 'maintenance_upcoming',
                    'days_until_due' => $daysUntilDue < PHP_INT_MAX ? $daysUntilDue : null,
                    'reason' => implode('; ', $reason),
                ];
            });

        // TODO: Odometer-based alerts
        // Check next_service_km from maintenance records and compare with current odometer_km
        // For now, we include date-based only per the current schema

        return [
            'overdue' => $dateBasedOverdue,
            'upcoming' => $dateBasedUpcoming,
            'total' => $dateBasedOverdue->count() + $dateBasedUpcoming->count(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  Vehicle Alerts — Insurance
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get insurance expiry alerts from vehicle_documents (type='insurance').
     *
     * @param  Carbon  $today  Reference date
     * @param  Carbon  $futureDate  Upcoming threshold
     * @return array Overdue and upcoming insurance alerts
     */
    public function getInsuranceAlerts(Carbon $today, Carbon $futureDate): array
    {
        $overdue = VehicleDocument::where('document_type', 'insurance')
            ->where('status', '!=', 'archived')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $today)
            ->with('vehicle')
            ->get()
            ->filter(fn ($doc) => $doc->vehicle && ! in_array($doc->vehicle->status, ['retired', 'sold']))
            ->map(function (VehicleDocument $doc) use ($today) {
                return [
                    'document_id' => $doc->id,
                    'vehicle_id' => $doc->vehicle_id,
                    'fleet_code' => $doc->vehicle->fleet_code,
                    'plate_number' => $doc->vehicle->plate_number,
                    'label' => $doc->vehicle->label,
                    'document_title' => $doc->title,
                    'document_number' => $doc->document_number,
                    'issuer' => $doc->issuer,
                    'expiry_date' => $doc->expiry_date->format('Y-m-d'),
                    'days_overdue' => $today->diffInDays($doc->expiry_date),
                    'alert_type' => 'insurance_expired',
                ];
            });

        $upcoming = VehicleDocument::where('document_type', 'insurance')
            ->where('status', '!=', 'archived')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', $today)
            ->where('expiry_date', '<=', $futureDate)
            ->with('vehicle')
            ->get()
            ->filter(fn ($doc) => $doc->vehicle && ! in_array($doc->vehicle->status, ['retired', 'sold']))
            ->map(function (VehicleDocument $doc) use ($today) {
                return [
                    'document_id' => $doc->id,
                    'vehicle_id' => $doc->vehicle_id,
                    'fleet_code' => $doc->vehicle->fleet_code,
                    'plate_number' => $doc->vehicle->plate_number,
                    'label' => $doc->vehicle->label,
                    'document_title' => $doc->title,
                    'document_number' => $doc->document_number,
                    'issuer' => $doc->issuer,
                    'expiry_date' => $doc->expiry_date->format('Y-m-d'),
                    'days_until_expiry' => $today->diffInDays($doc->expiry_date, false),
                    'alert_type' => 'insurance_expiring',
                ];
            });

        return [
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'total' => $overdue->count() + $upcoming->count(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  Vehicle Alerts — Registration
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get registration expiry alerts from vehicle_documents (type='registration').
     *
     * @param  Carbon  $today  Reference date
     * @param  Carbon  $futureDate  Upcoming threshold
     * @return array Overdue and upcoming registration alerts
     */
    public function getRegistrationAlerts(Carbon $today, Carbon $futureDate): array
    {
        $overdue = VehicleDocument::where('document_type', 'registration')
            ->where('status', '!=', 'archived')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $today)
            ->with('vehicle')
            ->get()
            ->filter(fn ($doc) => $doc->vehicle && ! in_array($doc->vehicle->status, ['retired', 'sold']))
            ->map(function (VehicleDocument $doc) use ($today) {
                return [
                    'document_id' => $doc->id,
                    'vehicle_id' => $doc->vehicle_id,
                    'fleet_code' => $doc->vehicle->fleet_code,
                    'plate_number' => $doc->vehicle->plate_number,
                    'label' => $doc->vehicle->label,
                    'document_title' => $doc->title,
                    'document_number' => $doc->document_number,
                    'issuer' => $doc->issuer,
                    'expiry_date' => $doc->expiry_date->format('Y-m-d'),
                    'days_overdue' => $today->diffInDays($doc->expiry_date),
                    'alert_type' => 'registration_expired',
                ];
            });

        $upcoming = VehicleDocument::where('document_type', 'registration')
            ->where('status', '!=', 'archived')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', $today)
            ->where('expiry_date', '<=', $futureDate)
            ->with('vehicle')
            ->get()
            ->filter(fn ($doc) => $doc->vehicle && ! in_array($doc->vehicle->status, ['retired', 'sold']))
            ->map(function (VehicleDocument $doc) use ($today) {
                return [
                    'document_id' => $doc->id,
                    'vehicle_id' => $doc->vehicle_id,
                    'fleet_code' => $doc->vehicle->fleet_code,
                    'plate_number' => $doc->vehicle->plate_number,
                    'label' => $doc->vehicle->label,
                    'document_title' => $doc->title,
                    'document_number' => $doc->document_number,
                    'issuer' => $doc->issuer,
                    'expiry_date' => $doc->expiry_date->format('Y-m-d'),
                    'days_until_expiry' => $today->diffInDays($doc->expiry_date, false),
                    'alert_type' => 'registration_expiring',
                ];
            });

        return [
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'total' => $overdue->count() + $upcoming->count(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  Vehicle Alerts — Other Documents
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get alerts for other vehicle documents (technical_inspection, rental_contract, etc.).
     *
     * @param  Carbon  $today  Reference date
     * @param  Carbon  $futureDate  Upcoming threshold
     * @return array Overdue and upcoming document alerts
     */
    public function getVehicleDocumentAlerts(Carbon $today, Carbon $futureDate): array
    {
        $overdue = VehicleDocument::whereNotIn('document_type', ['insurance', 'registration'])
            ->where('status', '!=', 'archived')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $today)
            ->with('vehicle')
            ->get()
            ->filter(fn ($doc) => $doc->vehicle && ! in_array($doc->vehicle->status, ['retired', 'sold']))
            ->map(function (VehicleDocument $doc) use ($today) {
                return [
                    'document_id' => $doc->id,
                    'vehicle_id' => $doc->vehicle_id,
                    'fleet_code' => $doc->vehicle->fleet_code,
                    'plate_number' => $doc->vehicle->plate_number,
                    'label' => $doc->vehicle->label,
                    'document_type' => $doc->document_type,
                    'document_title' => $doc->title,
                    'document_number' => $doc->document_number,
                    'expiry_date' => $doc->expiry_date->format('Y-m-d'),
                    'days_overdue' => $today->diffInDays($doc->expiry_date),
                    'alert_type' => 'document_expired',
                ];
            });

        $upcoming = VehicleDocument::whereNotIn('document_type', ['insurance', 'registration'])
            ->where('status', '!=', 'archived')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', $today)
            ->where('expiry_date', '<=', $futureDate)
            ->with('vehicle')
            ->get()
            ->filter(fn ($doc) => $doc->vehicle && ! in_array($doc->vehicle->status, ['retired', 'sold']))
            ->map(function (VehicleDocument $doc) use ($today) {
                return [
                    'document_id' => $doc->id,
                    'vehicle_id' => $doc->vehicle_id,
                    'fleet_code' => $doc->vehicle->fleet_code,
                    'plate_number' => $doc->vehicle->plate_number,
                    'label' => $doc->vehicle->label,
                    'document_type' => $doc->document_type,
                    'document_title' => $doc->title,
                    'document_number' => $doc->document_number,
                    'expiry_date' => $doc->expiry_date->format('Y-m-d'),
                    'days_until_expiry' => $today->diffInDays($doc->expiry_date, false),
                    'alert_type' => 'document_expiring',
                ];
            });

        return [
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'total' => $overdue->count() + $upcoming->count(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  Driver Alerts
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get driver license expiry alerts.
     *
     * @param  Carbon  $today  Reference date
     * @param  Carbon  $futureDate  Upcoming threshold
     * @return array Overdue and upcoming license alerts
     */
    public function getDriverLicenseAlerts(Carbon $today, Carbon $futureDate): array
    {
        $overdue = ApprovedDriver::where('authorization_status', '!=', 'revoked')
            ->whereNotNull('license_expiry_date')
            ->where('license_expiry_date', '<', $today)
            ->with('employee.user')
            ->get()
            ->map(function (ApprovedDriver $driver) use ($today) {
                return [
                    'driver_id' => $driver->id,
                    'driver_authorization_number' => $driver->driver_authorization_number,
                    'employee_id' => $driver->employee_id,
                    'employee_name' => $driver->employee->user->name ?? 'Unknown',
                    'license_number' => $driver->license_number,
                    'license_expiry_date' => $driver->license_expiry_date->format('Y-m-d'),
                    'days_overdue' => $today->diffInDays($driver->license_expiry_date),
                    'alert_type' => 'license_expired',
                ];
            });

        $upcoming = ApprovedDriver::where('authorization_status', '!=', 'revoked')
            ->whereNotNull('license_expiry_date')
            ->where('license_expiry_date', '>=', $today)
            ->where('license_expiry_date', '<=', $futureDate)
            ->with('employee.user')
            ->get()
            ->map(function (ApprovedDriver $driver) use ($today) {
                return [
                    'driver_id' => $driver->id,
                    'driver_authorization_number' => $driver->driver_authorization_number,
                    'employee_id' => $driver->employee_id,
                    'employee_name' => $driver->employee->user->name ?? 'Unknown',
                    'license_number' => $driver->license_number,
                    'license_expiry_date' => $driver->license_expiry_date->format('Y-m-d'),
                    'days_until_expiry' => $today->diffInDays($driver->license_expiry_date, false),
                    'alert_type' => 'license_expiring',
                ];
            });

        return [
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'total' => $overdue->count() + $upcoming->count(),
        ];
    }

    /**
     * Get driver authorization expiry alerts.
     *
     * @param  Carbon  $today  Reference date
     * @param  Carbon  $futureDate  Upcoming threshold
     * @return array Overdue and upcoming authorization alerts
     */
    public function getDriverAuthorizationAlerts(Carbon $today, Carbon $futureDate): array
    {
        $overdue = ApprovedDriver::where('authorization_status', 'approved')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $today)
            ->with('employee.user')
            ->get()
            ->map(function (ApprovedDriver $driver) use ($today) {
                return [
                    'driver_id' => $driver->id,
                    'driver_authorization_number' => $driver->driver_authorization_number,
                    'employee_id' => $driver->employee_id,
                    'employee_name' => $driver->employee->user->name ?? 'Unknown',
                    'expiry_date' => $driver->expiry_date->format('Y-m-d'),
                    'days_overdue' => $today->diffInDays($driver->expiry_date),
                    'alert_type' => 'authorization_expired',
                ];
            });

        $upcoming = ApprovedDriver::where('authorization_status', 'approved')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', $today)
            ->where('expiry_date', '<=', $futureDate)
            ->with('employee.user')
            ->get()
            ->map(function (ApprovedDriver $driver) use ($today) {
                return [
                    'driver_id' => $driver->id,
                    'driver_authorization_number' => $driver->driver_authorization_number,
                    'employee_id' => $driver->employee_id,
                    'employee_name' => $driver->employee->user->name ?? 'Unknown',
                    'expiry_date' => $driver->expiry_date->format('Y-m-d'),
                    'days_until_expiry' => $today->diffInDays($driver->expiry_date, false),
                    'alert_type' => 'authorization_expiring',
                ];
            });

        return [
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'total' => $overdue->count() + $upcoming->count(),
        ];
    }

    /**
     * Get driver authorization review alerts.
     *
     * @param  Carbon  $today  Reference date
     * @param  Carbon  $futureDate  Upcoming threshold
     * @return array Overdue and upcoming review alerts
     */
    public function getDriverReviewAlerts(Carbon $today, Carbon $futureDate): array
    {
        $overdue = ApprovedDriver::where('authorization_status', 'approved')
            ->whereNotNull('review_date')
            ->where('review_date', '<', $today)
            ->with('employee.user')
            ->get()
            ->map(function (ApprovedDriver $driver) use ($today) {
                return [
                    'driver_id' => $driver->id,
                    'driver_authorization_number' => $driver->driver_authorization_number,
                    'employee_id' => $driver->employee_id,
                    'employee_name' => $driver->employee->user->name ?? 'Unknown',
                    'review_date' => $driver->review_date->format('Y-m-d'),
                    'days_overdue' => $today->diffInDays($driver->review_date),
                    'alert_type' => 'review_overdue',
                ];
            });

        $upcoming = ApprovedDriver::where('authorization_status', 'approved')
            ->whereNotNull('review_date')
            ->where('review_date', '>=', $today)
            ->where('review_date', '<=', $futureDate)
            ->with('employee.user')
            ->get()
            ->map(function (ApprovedDriver $driver) use ($today) {
                return [
                    'driver_id' => $driver->id,
                    'driver_authorization_number' => $driver->driver_authorization_number,
                    'employee_id' => $driver->employee_id,
                    'employee_name' => $driver->employee->user->name ?? 'Unknown',
                    'review_date' => $driver->review_date->format('Y-m-d'),
                    'days_until_review' => $today->diffInDays($driver->review_date, false),
                    'alert_type' => 'review_upcoming',
                ];
            });

        return [
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'total' => $overdue->count() + $upcoming->count(),
        ];
    }

    /**
     * Get currently suspended drivers.
     *
     * @return Collection Suspended drivers where suspension is currently active
     */
    public function getSuspendedDriverAlerts(): Collection
    {
        $today = Carbon::today();

        return ApprovedDriver::where('authorization_status', 'suspended')
            ->orWhere(function ($q) use ($today) {
                $q->whereNotNull('suspended_until')
                    ->where('suspended_until', '>=', $today);
            })
            ->with('employee.user')
            ->get()
            ->map(function (ApprovedDriver $driver) {
                return [
                    'driver_id' => $driver->id,
                    'driver_authorization_number' => $driver->driver_authorization_number,
                    'employee_id' => $driver->employee_id,
                    'employee_name' => $driver->employee->user->name ?? 'Unknown',
                    'suspended_from' => $driver->suspended_from?->format('Y-m-d'),
                    'suspended_until' => $driver->suspended_until?->format('Y-m-d'),
                    'suspension_reason' => $driver->suspension_reason,
                    'is_indefinite' => ! $driver->suspended_until,
                    'alert_type' => 'driver_suspended',
                ];
            });
    }

    /**
     * Get revoked drivers.
     *
     * @return Collection Drivers with revoked authorization
     */
    public function getRevokedDriverAlerts(): Collection
    {
        return ApprovedDriver::where('authorization_status', 'revoked')
            ->with('employee.user')
            ->get()
            ->map(function (ApprovedDriver $driver) {
                return [
                    'driver_id' => $driver->id,
                    'driver_authorization_number' => $driver->driver_authorization_number,
                    'employee_id' => $driver->employee_id,
                    'employee_name' => $driver->employee->user->name ?? 'Unknown',
                    'alert_type' => 'driver_revoked',
                ];
            });
    }
}
