<?php

namespace App\Services;

use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleAssignment;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Logistics\Models\DeliveryServiceProvider;
use App\Modules\OperationalHub\Models\WorkshopUnit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resource Validation Service
 *
 * Validates operational resource assignments for DWO/SWO before dispatch.
 * Checks driver authorization, vehicle availability, compatibility, maintenance,
 * insurance, assignments, and reservations.
 *
 * Central service used by Logistics and Operational Hub to ensure resources
 * are genuinely eligible before operational assignment.
 */
class ResourceValidationService
{
    /**
     * Validate all resources for a delivery work order.
     *
     * Checks vehicle, driver, mobile warehouse, delivery provider,
     * and compatibility before DWO assignment or dispatch.
     *
     * @param  array  $resources  Resource identifiers
     * @param  Carbon|null  $scheduledDate  Scheduled delivery date (default: now)
     * @param  Carbon|null  $endDate  Optional end date for multi-day operations
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateDwoResources(
        array $resources,
        ?Carbon $scheduledDate = null,
        ?Carbon $endDate = null
    ): array {
        $errors = [];
        $scheduledDate = $scheduledDate ?? now();

        // Validate vehicle
        if (! empty($resources['vehicle_id'])) {
            $vehicleErrors = $this->validateVehicleAvailability(
                $resources['vehicle_id'],
                $scheduledDate,
                $endDate
            );
            if (! empty($vehicleErrors)) {
                $errors['vehicle'] = $vehicleErrors;
            }
        }

        // Validate approved driver
        if (! empty($resources['approved_driver_id'])) {
            $driverErrors = $this->validateDriverAuthorization(
                $resources['approved_driver_id'],
                $resources['vehicle_id'] ?? null,
                $scheduledDate
            );
            if (! empty($driverErrors)) {
                $errors['driver'] = $driverErrors;
            }
        }

        // Validate mobile warehouse
        if (! empty($resources['mobile_warehouse_id'])) {
            $warehouseErrors = $this->validateMobileWarehouse(
                $resources['mobile_warehouse_id'],
                $resources['vehicle_id'] ?? null
            );
            if (! empty($warehouseErrors)) {
                $errors['mobile_warehouse'] = $warehouseErrors;
            }
        }

        // Validate delivery provider
        if (! empty($resources['delivery_provider_id'])) {
            $providerErrors = $this->validateDeliveryProvider(
                $resources['delivery_provider_id']
            );
            if (! empty($providerErrors)) {
                $errors['delivery_provider'] = $providerErrors;
            }
        }

        // Check vehicle-driver compatibility
        if (! empty($resources['vehicle_id']) && ! empty($resources['approved_driver_id'])) {
            $compatibilityErrors = $this->validateVehicleDriverCompatibility(
                $resources['vehicle_id'],
                $resources['approved_driver_id']
            );
            if (! empty($compatibilityErrors)) {
                $errors['compatibility'] = $compatibilityErrors;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate all resources for a service work order.
     *
     * Checks vehicle, driver, workshop unit, mobile warehouse,
     * and compatibility before SWO assignment or dispatch.
     *
     * @param  array  $resources  Resource identifiers
     * @param  Carbon|null  $scheduledDate  Scheduled service date (default: now)
     * @param  Carbon|null  $endDate  Optional end date for multi-day operations
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateSwoResources(
        array $resources,
        ?Carbon $scheduledDate = null,
        ?Carbon $endDate = null
    ): array {
        $errors = [];
        $scheduledDate = $scheduledDate ?? now();

        // Validate vehicle (for field service)
        if (! empty($resources['vehicle_id'])) {
            $vehicleErrors = $this->validateVehicleAvailability(
                $resources['vehicle_id'],
                $scheduledDate,
                $endDate
            );
            if (! empty($vehicleErrors)) {
                $errors['vehicle'] = $vehicleErrors;
            }
        }

        // Validate approved driver (for field service)
        if (! empty($resources['approved_driver_id'])) {
            $driverErrors = $this->validateDriverAuthorization(
                $resources['approved_driver_id'],
                $resources['vehicle_id'] ?? null,
                $scheduledDate
            );
            if (! empty($driverErrors)) {
                $errors['driver'] = $driverErrors;
            }
        }

        // Validate workshop unit
        if (! empty($resources['workshop_unit_id'])) {
            $workshopErrors = $this->validateWorkshopUnit(
                $resources['workshop_unit_id']
            );
            if (! empty($workshopErrors)) {
                $errors['workshop_unit'] = $workshopErrors;
            }
        }

        // Validate mobile warehouse
        if (! empty($resources['mobile_warehouse_id'])) {
            $warehouseErrors = $this->validateMobileWarehouse(
                $resources['mobile_warehouse_id'],
                $resources['vehicle_id'] ?? null
            );
            if (! empty($warehouseErrors)) {
                $errors['mobile_warehouse'] = $warehouseErrors;
            }
        }

        // Check vehicle-driver compatibility
        if (! empty($resources['vehicle_id']) && ! empty($resources['approved_driver_id'])) {
            $compatibilityErrors = $this->validateVehicleDriverCompatibility(
                $resources['vehicle_id'],
                $resources['approved_driver_id']
            );
            if (! empty($compatibilityErrors)) {
                $errors['compatibility'] = $compatibilityErrors;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate vehicle availability and status for operational use.
     *
     * Comprehensive validation including status, maintenance, insurance,
     * registration, rental/client status, and conflicting assignments.
     *
     * @param  string  $vehicleId  Vehicle ULID
     * @param  Carbon  $date  Operational date to check
     * @param  Carbon|null  $endDate  Optional end date for range checking
     * @return array List of validation errors (empty if valid)
     */
    public function validateVehicleAvailability(
        string $vehicleId,
        Carbon $date,
        ?Carbon $endDate = null
    ): array {
        $errors = [];

        $vehicle = Vehicle::find($vehicleId);

        if (! $vehicle) {
            $errors[] = 'Vehicle not found';

            return $errors;
        }

        // ─── Status Validation ───────────────────────────────────────

        // Prevent using vehicles that are operationally unavailable
        $nonOperationalStatuses = ['under_maintenance', 'under_repair', 'out_of_service', 'retired', 'written_off'];
        if (in_array($vehicle->status, $nonOperationalStatuses)) {
            $errors[] = "Vehicle is currently {$vehicle->status}";
        }

        // Available or in_use are acceptable (in_use can be re-assigned)
        if (! in_array($vehicle->status, ['available', 'in_use', ...$nonOperationalStatuses])) {
            $errors[] = "Vehicle status '{$vehicle->status}' is not operational";
        }

        // ─── Ownership & Usage Validation ────────────────────────────

        // Client vehicles
        if ($vehicle->ownership_type === 'client') {
            if ($vehicle->client_vehicle_status !== 'active') {
                $errors[] = "Client vehicle status is '{$vehicle->client_vehicle_status}', not active";
            }

            // Check client usage period
            if ($vehicle->client_start_date && $date->lt($vehicle->client_start_date)) {
                $errors[] = 'Client vehicle usage period has not started yet';
            }

            if ($vehicle->client_end_date && $date->gt($vehicle->client_end_date)) {
                $errors[] = 'Client vehicle usage period has ended';
            }
        }

        // Rental vehicles
        if ($vehicle->ownership_type === 'rental') {
            if ($vehicle->rental_status !== 'active') {
                $errors[] = "Rental vehicle status is '{$vehicle->rental_status}', not active";
            }

            // Check rental period
            if ($vehicle->rental_start_date && $date->lt($vehicle->rental_start_date)) {
                $errors[] = 'Rental period has not started yet';
            }

            if ($vehicle->rental_end_date && $date->gt($vehicle->rental_end_date)) {
                $errors[] = 'Rental period has ended or will end soon';
            }
        }

        // ─── Maintenance & Inspection ────────────────────────────────

        // Check if overdue for scheduled maintenance
        if ($vehicle->next_service_date && $vehicle->next_service_date->lt($date)) {
            $daysPast = $date->diffInDays($vehicle->next_service_date);
            $errors[] = "Vehicle is overdue for maintenance by {$daysPast} day(s)";
        }

        // Check if overdue for inspection
        if ($vehicle->next_inspection_date && $vehicle->next_inspection_date->lt($date)) {
            $daysPast = $date->diffInDays($vehicle->next_inspection_date);
            $errors[] = "Vehicle inspection is overdue by {$daysPast} day(s)";
        }

        // ─── Insurance & Registration ────────────────────────────────

        // Insurance must be valid
        if ($vehicle->insurance_expiry_date && $vehicle->insurance_expiry_date->lt($date)) {
            $errors[] = 'Vehicle insurance has expired';
        }

        // Registration must be valid
        if ($vehicle->registration_expiry_date && $vehicle->registration_expiry_date->lt($date)) {
            $errors[] = 'Vehicle registration has expired';
        }

        // ─── Assignment & Reservation Conflicts ──────────────────────

        // Check for conflicting assignments
        $conflictingAssignments = $this->checkVehicleAssignmentConflicts($vehicleId, $date, $endDate);
        if ($conflictingAssignments->isNotEmpty()) {
            $errors[] = 'Vehicle has conflicting assignment(s) on this date';
        }

        // TODO: Add reservation conflict checking when vehicle reservation model exists
        // $conflictingReservations = $this->checkVehicleReservationConflicts($vehicleId, $date, $endDate);
        // if ($conflictingReservations->isNotEmpty()) {
        //     $errors[] = "Vehicle has conflicting reservation(s) for this period";
        // }

        return $errors;
    }

    /**
     * Validate driver authorization and eligibility for operational use.
     *
     * Comprehensive validation including authorization status, suspension,
     * expiry dates, license validity, and vehicle-specific permissions.
     *
     * @param  string  $driverId  ApprovedDriver ULID
     * @param  string|null  $vehicleId  Optional vehicle ULID for compatibility checking
     * @param  Carbon  $date  Operational date to check
     * @return array List of validation errors (empty if valid)
     */
    public function validateDriverAuthorization(
        string $driverId,
        ?string $vehicleId,
        Carbon $date
    ): array {
        $errors = [];

        $driver = ApprovedDriver::with('employee')->find($driverId);

        if (! $driver) {
            $errors[] = 'Approved driver not found';

            return $errors;
        }

        // ─── Authorization Status ────────────────────────────────────

        // Only 'approved' drivers are operational
        if ($driver->authorization_status !== 'approved') {
            $errors[] = "Driver authorization status is '{$driver->authorization_status}', not approved";
        }

        if (! $driver->is_active) {
            $errors[] = 'Driver authorization is inactive';
        }

        // ─── Suspension Check ────────────────────────────────────────

        if ($driver->is_suspended) {
            if ($driver->suspended_until) {
                // Check if suspension has ended
                if ($date->lt($driver->suspended_until)) {
                    $suspendedUntil = $driver->suspended_until->format('Y-m-d');
                    $errors[] = "Driver is suspended until {$suspendedUntil}";
                }
                // If date is past suspended_until, driver should have been unsuspended
                // but we still flag it to ensure administrative review
                elseif ($driver->is_suspended) {
                    $errors[] = 'Driver suspension period has ended but status is still suspended';
                }
            } else {
                $errors[] = 'Driver is suspended indefinitely';
            }
        }

        // ─── Expiry Validation ───────────────────────────────────────

        // Driver authorization expiry
        if ($driver->expiry_date && $driver->expiry_date->lt($date)) {
            $errors[] = 'Driver authorization has expired on '.$driver->expiry_date->format('Y-m-d');
        }

        // Driving license expiry
        if ($driver->license_expiry_date && $driver->license_expiry_date->lt($date)) {
            $errors[] = 'Driver license has expired on '.$driver->license_expiry_date->format('Y-m-d');
        }

        // ─── Vehicle-Specific Validation ─────────────────────────────

        if ($vehicleId) {
            $vehicle = Vehicle::find($vehicleId);
            if ($vehicle) {
                // Check vehicle type authorization
                if (! $driver->canDriveVehicleType($vehicle->type)) {
                    $allowed = $driver->permitted_vehicle_types
                        ? implode(', ', $driver->permitted_vehicle_types)
                        : 'none';
                    $errors[] = "Driver not authorized for vehicle type '{$vehicle->type}' (allowed: {$allowed})";
                }

                // Check vehicle class authorization
                if ($vehicle->vehicle_class && ! $driver->canDriveVehicleClass($vehicle->vehicle_class)) {
                    $allowed = $driver->permitted_vehicle_classes
                        ? implode(', ', $driver->permitted_vehicle_classes)
                        : 'none';
                    $errors[] = "Driver not authorized for vehicle class '{$vehicle->vehicle_class}' (allowed: {$allowed})";
                }

                // Check rental vehicle permission
                if ($vehicle->ownership_type === 'rental' && ! $driver->can_drive_rental) {
                    $errors[] = 'Driver is not authorized to drive rental vehicles';
                }

                // Check client vehicle permission
                if ($vehicle->ownership_type === 'client' && ! $driver->can_drive_client_vehicles) {
                    $errors[] = 'Driver is not authorized to drive client vehicles';
                }

                // Check cargo weight restrictions
                if ($driver->max_cargo_kg && $vehicle->capacity_kg > $driver->max_cargo_kg) {
                    $errors[] = "Vehicle capacity ({$vehicle->capacity_kg} kg) exceeds driver limit ({$driver->max_cargo_kg} kg)";
                }

                // Check passenger restrictions
                if ($driver->max_passengers && $vehicle->seats > $driver->max_passengers) {
                    $errors[] = "Vehicle seats ({$vehicle->seats}) exceed driver passenger limit ({$driver->max_passengers})";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate vehicle-driver compatibility
     */
    public function validateVehicleDriverCompatibility(string $vehicleId, string $driverId): array
    {
        $errors = [];

        $vehicle = Vehicle::find($vehicleId);
        $driver = ApprovedDriver::find($driverId);

        if (! $vehicle || ! $driver) {
            return $errors; // Already handled in individual validations
        }

        // Check weight restrictions
        if ($driver->max_cargo_kg && $vehicle->capacity_kg > $driver->max_cargo_kg) {
            $errors[] = "Vehicle capacity ({$vehicle->capacity_kg} kg) exceeds driver's limit ({$driver->max_cargo_kg} kg)";
        }

        // Check passenger restrictions
        if ($driver->max_passengers && $vehicle->seats > $driver->max_passengers) {
            $errors[] = "Vehicle seats ({$vehicle->seats}) exceeds driver's passenger limit ({$driver->max_passengers})";
        }

        return $errors;
    }

    /**
     * Validate mobile warehouse
     */
    public function validateMobileWarehouse(string $warehouseId, ?string $vehicleId = null): array
    {
        $errors = [];

        $warehouse = WarehouseSite::find($warehouseId);

        if (! $warehouse) {
            $errors[] = 'Mobile warehouse not found';

            return $errors;
        }

        if ($warehouse->site_type !== 'mobile') {
            $errors[] = 'Warehouse is not a mobile type';

            return $errors;
        }

        if ($warehouse->status !== 'active') {
            $errors[] = "Mobile warehouse is {$warehouse->status}";
        }

        if ($warehouse->mobile_status && ! in_array($warehouse->mobile_status, ['active', 'in_transit'])) {
            $errors[] = "Mobile warehouse operational status is {$warehouse->mobile_status}";
        }

        // Check vehicle assignment if vehicle provided
        if ($vehicleId && $warehouse->vehicle_id && $warehouse->vehicle_id !== $vehicleId) {
            $errors[] = 'Mobile warehouse is assigned to a different vehicle';
        }

        if (! $warehouse->vehicle_id) {
            $errors[] = 'Mobile warehouse has no vehicle assigned';
        }

        return $errors;
    }

    /**
     * Validate workshop unit
     */
    public function validateWorkshopUnit(string $workshopUnitId): array
    {
        $errors = [];

        $workshop = WorkshopUnit::find($workshopUnitId);

        if (! $workshop) {
            $errors[] = 'Workshop unit not found';

            return $errors;
        }

        if ($workshop->status->value !== 'active') {
            $errors[] = "Workshop unit is {$workshop->status->value}";
        }

        return $errors;
    }

    /**
     * Validate delivery service provider
     */
    public function validateDeliveryProvider(string $providerId): array
    {
        $errors = [];

        $provider = DeliveryServiceProvider::with('serviceProvider')->find($providerId);

        if (! $provider) {
            $errors[] = 'Delivery service provider not found';

            return $errors;
        }

        if (! $provider->is_active) {
            $errors[] = "Delivery provider is {$provider->operational_status}";
        }

        if (! $provider->is_accepting_orders) {
            $errors[] = 'Delivery provider is not accepting new orders';
        }

        // Check service provider BP status
        if ($provider->serviceProvider && $provider->serviceProvider->status !== 'active') {
            $errors[] = "Service provider business partner is {$provider->serviceProvider->status}";
        }

        return $errors;
    }

    // ═══════════════════════════════════════════════════════════════
    // Conflict & Availability Checking
    // ═══════════════════════════════════════════════════════════════

    /**
     * Check for conflicting vehicle assignments in a given period.
     *
     * Returns assignments that overlap with the requested period.
     *
     * @param  string  $vehicleId  Vehicle ULID
     * @param  Carbon  $startDate  Start of period to check
     * @param  Carbon|null  $endDate  End of period (null = single point in time)
     * @return Collection Collection of conflicting VehicleAssignment models
     */
    public function checkVehicleAssignmentConflicts(
        string $vehicleId,
        Carbon $startDate,
        ?Carbon $endDate = null
    ): Collection {
        $endDate = $endDate ?? $startDate->copy()->endOfDay();

        return VehicleAssignment::where('vehicle_id', $vehicleId)
            ->where('is_current', true)
            ->where(function ($query) use ($startDate, $endDate) {
                // Assignment overlaps if:
                // (assignment_start <= requested_end) AND (assignment_end >= requested_start OR assignment_end IS NULL)
                $query->where('start_date', '<=', $endDate)
                    ->where(function ($q) use ($startDate) {
                        $q->where('end_date', '>=', $startDate)
                            ->orWhereNull('end_date'); // Current assignment with no end date
                    });
            })
            ->get();
    }

    /**
     * Check if a vehicle is genuinely available for operational use.
     *
     * Combines status checks, validation, and conflict checking.
     *
     * @param  string  $vehicleId  Vehicle ULID
     * @param  Carbon  $date  Operational date
     * @param  Carbon|null  $endDate  Optional end date for period check
     * @return bool True if available, false otherwise
     */
    public function isVehicleAvailable(
        string $vehicleId,
        Carbon $date,
        ?Carbon $endDate = null
    ): bool {
        $errors = $this->validateVehicleAvailability($vehicleId, $date, $endDate);

        return empty($errors);
    }

    /**
     * Check if a driver is genuinely available for operational use.
     *
     * Combines authorization checks and validation.
     *
     * @param  string  $driverId  ApprovedDriver ULID
     * @param  string|null  $vehicleId  Optional vehicle ULID for compatibility
     * @param  Carbon  $date  Operational date
     * @return bool True if available, false otherwise
     */
    public function isDriverAvailable(
        string $driverId,
        ?string $vehicleId,
        Carbon $date
    ): bool {
        $errors = $this->validateDriverAuthorization($driverId, $vehicleId, $date);

        return empty($errors);
    }

    // ═══════════════════════════════════════════════════════════════
    // Transactional Validation (Pre-Dispatch)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Validate resources transactionally before dispatch.
     *
     * This method should be called within a database transaction,
     * immediately before creating dispatch/assignment records.
     *
     * It performs final validation with locking to prevent race conditions.
     *
     * @param  array  $resources  Resource identifiers (vehicle_id, approved_driver_id, etc.)
     * @param  Carbon  $scheduledDate  Scheduled operational date
     * @param  Carbon|null  $endDate  Optional end date for period validation
     * @return array ['valid' => bool, 'errors' => array]
     *
     * @throws \RuntimeException if called outside a transaction
     */
    public function validateResourcesForDispatch(
        array $resources,
        Carbon $scheduledDate,
        ?Carbon $endDate = null
    ): array {
        // Ensure this is called within a transaction
        if (DB::transactionLevel() === 0) {
            throw new \RuntimeException(
                'validateResourcesForDispatch must be called within a database transaction'
            );
        }

        $errors = [];

        // Lock and validate vehicle
        if (! empty($resources['vehicle_id'])) {
            $vehicle = Vehicle::lockForUpdate()->find($resources['vehicle_id']);

            if (! $vehicle) {
                $errors['vehicle'][] = 'Vehicle not found';
            } else {
                $vehicleErrors = $this->validateVehicleAvailability(
                    $resources['vehicle_id'],
                    $scheduledDate,
                    $endDate
                );

                if (! empty($vehicleErrors)) {
                    $errors['vehicle'] = $vehicleErrors;
                }
            }
        }

        // Lock and validate driver
        if (! empty($resources['approved_driver_id'])) {
            $driver = ApprovedDriver::lockForUpdate()->find($resources['approved_driver_id']);

            if (! $driver) {
                $errors['driver'][] = 'Approved driver not found';
            } else {
                $driverErrors = $this->validateDriverAuthorization(
                    $resources['approved_driver_id'],
                    $resources['vehicle_id'] ?? null,
                    $scheduledDate
                );

                if (! empty($driverErrors)) {
                    $errors['driver'] = $driverErrors;
                }
            }
        }

        // Check vehicle-driver compatibility
        if (! empty($resources['vehicle_id']) && ! empty($resources['approved_driver_id'])) {
            $compatibilityErrors = $this->validateVehicleDriverCompatibility(
                $resources['vehicle_id'],
                $resources['approved_driver_id']
            );

            if (! empty($compatibilityErrors)) {
                $errors['compatibility'] = $compatibilityErrors;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // Improved Availability Queries
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get genuinely available drivers for a vehicle on a specific date.
     *
     * Returns only drivers who are:
     * - Authorized and not suspended
     * - Compatible with the vehicle
     * - Not expired
     * - Available on the requested date
     *
     * @param  string  $vehicleId  Vehicle ULID
     * @param  Carbon|null  $date  Operational date (default: now)
     * @return Collection Collection of ApprovedDriver models
     */
    public function getAvailableDriversForVehicle(string $vehicleId, ?Carbon $date = null): Collection
    {
        $date = $date ?? now();
        $vehicle = Vehicle::find($vehicleId);

        if (! $vehicle) {
            return collect();
        }

        return ApprovedDriver::where('authorization_status', 'approved')
            ->where('is_active', true)
            ->where(function ($query) use ($date) {
                // Not suspended, or suspension has ended
                $query->where('is_suspended', false)
                    ->orWhere(function ($q) use ($date) {
                        $q->where('is_suspended', true)
                            ->where('suspended_until', '<', $date);
                    });
            })
            ->where(function ($query) use ($date) {
                // Authorization not expired
                $query->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', $date);
            })
            ->where(function ($query) use ($date) {
                // License not expired
                $query->whereNull('license_expiry_date')
                    ->orWhere('license_expiry_date', '>=', $date);
            })
            ->where(function ($query) use ($vehicle) {
                // Permitted vehicle types
                $query->whereJsonContains('permitted_vehicle_types', $vehicle->type)
                    ->orWhereNull('permitted_vehicle_types');
            })
            ->when($vehicle->vehicle_class, function ($query) use ($vehicle) {
                $query->where(function ($q) use ($vehicle) {
                    $q->whereJsonContains('permitted_vehicle_classes', $vehicle->vehicle_class)
                        ->orWhereNull('permitted_vehicle_classes');
                });
            })
            ->when($vehicle->ownership_type === 'rental', function ($query) {
                $query->where('can_drive_rental', true);
            })
            ->when($vehicle->ownership_type === 'client', function ($query) {
                $query->where('can_drive_client_vehicles', true);
            })
            ->when($vehicle->capacity_kg, function ($query) use ($vehicle) {
                // Null max_cargo_kg means no restriction
                $query->where(function ($q) use ($vehicle) {
                    $q->whereNull('max_cargo_kg')
                        ->orWhere('max_cargo_kg', '>=', $vehicle->capacity_kg);
                });
            })
            ->when($vehicle->seats, function ($query) use ($vehicle) {
                // Null max_passengers means no restriction
                $query->where(function ($q) use ($vehicle) {
                    $q->whereNull('max_passengers')
                        ->orWhere('max_passengers', '>=', $vehicle->seats);
                });
            })
            ->with('employee')
            ->get();
    }

    /**
     * Get genuinely available vehicles for a driver on a specific date.
     *
     * Returns only vehicles that are:
     * - Operationally available (not under maintenance, etc.)
     * - Compatible with driver authorizations
     * - Not expired (insurance, registration)
     * - Available on the requested date (no conflicts)
     *
     * @param  string  $driverId  ApprovedDriver ULID
     * @param  Carbon|null  $date  Operational date (default: now)
     * @return Collection Collection of Vehicle models
     */
    public function getAvailableVehiclesForDriver(string $driverId, ?Carbon $date = null): Collection
    {
        $date = $date ?? now();
        $driver = ApprovedDriver::find($driverId);

        if (! $driver) {
            return collect();
        }

        return Vehicle::whereIn('status', ['available', 'in_use'])
            ->where(function ($query) use ($date) {
                // Insurance not expired
                $query->whereNull('insurance_expiry_date')
                    ->orWhere('insurance_expiry_date', '>=', $date);
            })
            ->where(function ($query) use ($date) {
                // Registration not expired
                $query->whereNull('registration_expiry_date')
                    ->orWhere('registration_expiry_date', '>=', $date);
            })
            ->where(function ($query) use ($date) {
                // Not overdue for maintenance
                $query->whereNull('next_service_date')
                    ->orWhere('next_service_date', '>=', $date);
            })
            ->where(function ($query) use ($date) {
                // Not overdue for inspection
                $query->whereNull('next_inspection_date')
                    ->orWhere('next_inspection_date', '>=', $date);
            })
            ->when($driver->permitted_vehicle_types, function ($query) use ($driver) {
                $query->whereIn('type', $driver->permitted_vehicle_types);
            })
            ->when($driver->permitted_vehicle_classes, function ($query) use ($driver) {
                $query->whereIn('vehicle_class', $driver->permitted_vehicle_classes);
            })
            ->when($driver->max_cargo_kg, function ($query) use ($driver) {
                $query->where('capacity_kg', '<=', $driver->max_cargo_kg);
            })
            ->when($driver->max_passengers, function ($query) use ($driver) {
                $query->where('seats', '<=', $driver->max_passengers);
            })
            ->when(! $driver->can_drive_rental, function ($query) {
                $query->where('ownership_type', '!=', 'rental');
            })
            ->when(! $driver->can_drive_client_vehicles, function ($query) {
                $query->where('ownership_type', '!=', 'client');
            })
            // Filter out vehicles with current assignments (conflicts)
            ->whereDoesntHave('assignments', function ($query) {
                $query->where('is_current', true);
            })
            ->get();
    }
}
