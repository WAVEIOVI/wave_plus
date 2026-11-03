<?php

namespace App\Services;

use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Logistics\Models\DeliveryServiceProvider;
use App\Modules\OperationalHub\Models\WorkshopUnit;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Resource Validation Service
 *
 * Validates operational resource assignments for DWO/SWO
 * Checks driver authorization, vehicle availability, compatibility, etc.
 */
class ResourceValidationService
{
    /**
     * Validate all resources for a delivery work order
     */
    public function validateDwoResources(array $resources, ?Carbon $scheduledDate = null): array
    {
        $errors = [];
        $scheduledDate = $scheduledDate ?? now();

        // Validate vehicle
        if (! empty($resources['vehicle_id'])) {
            $vehicleErrors = $this->validateVehicleAvailability(
                $resources['vehicle_id'],
                $scheduledDate
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
     * Validate all resources for a service work order
     */
    public function validateSwoResources(array $resources, ?Carbon $scheduledDate = null): array
    {
        $errors = [];
        $scheduledDate = $scheduledDate ?? now();

        // Validate vehicle (for field service)
        if (! empty($resources['vehicle_id'])) {
            $vehicleErrors = $this->validateVehicleAvailability(
                $resources['vehicle_id'],
                $scheduledDate
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
     * Validate vehicle availability and status
     */
    public function validateVehicleAvailability(string $vehicleId, Carbon $date): array
    {
        $errors = [];

        $vehicle = Vehicle::find($vehicleId);

        if (! $vehicle) {
            $errors[] = 'Vehicle not found';

            return $errors;
        }

        // Check vehicle status
        if (! in_array($vehicle->status, ['available', 'in_use'])) {
            $errors[] = "Vehicle is currently {$vehicle->status}";
        }

        // Check if company owned or rental (client vehicles may have restrictions)
        if ($vehicle->ownership_type === 'client' && $vehicle->client_vehicle_status !== 'active') {
            $errors[] = 'Client vehicle is not in active status';
        }

        if ($vehicle->ownership_type === 'rental' && $vehicle->rental_status !== 'active') {
            $errors[] = 'Rental vehicle is not in active status';
        }

        // Check maintenance needs
        if ($vehicle->next_service_date && $vehicle->next_service_date->isPast()) {
            $errors[] = 'Vehicle is overdue for maintenance';
        }

        // Check inspection
        if ($vehicle->next_inspection_date && $vehicle->next_inspection_date->isPast()) {
            $errors[] = 'Vehicle inspection is expired';
        }

        // Check insurance
        if ($vehicle->insurance_expiry_date && $vehicle->insurance_expiry_date->isPast()) {
            $errors[] = 'Vehicle insurance is expired';
        }

        // Check registration
        if ($vehicle->registration_expiry_date && $vehicle->registration_expiry_date->isPast()) {
            $errors[] = 'Vehicle registration is expired';
        }

        return $errors;
    }

    /**
     * Validate driver authorization
     */
    public function validateDriverAuthorization(string $driverId, ?string $vehicleId, Carbon $date): array
    {
        $errors = [];

        $driver = ApprovedDriver::with('employee')->find($driverId);

        if (! $driver) {
            $errors[] = 'Approved driver not found';

            return $errors;
        }

        // Check authorization status
        if (! $driver->is_active) {
            $errors[] = "Driver authorization is {$driver->authorization_status}";
        }

        // Check if suspended
        if ($driver->is_suspended) {
            $suspendedUntil = $driver->suspended_until ? $driver->suspended_until->format('Y-m-d') : 'indefinitely';
            $errors[] = "Driver is suspended until {$suspendedUntil}";
        }

        // Check expiry
        if ($driver->expiry_date && $driver->expiry_date->lt($date)) {
            $errors[] = 'Driver authorization has expired';
        }

        // Check license expiry
        if ($driver->license_expiry_date && $driver->license_expiry_date->lt($date)) {
            $errors[] = 'Driver license has expired';
        }

        // Check vehicle compatibility if vehicle provided
        if ($vehicleId) {
            $vehicle = Vehicle::find($vehicleId);
            if ($vehicle) {
                if (! $driver->canDriveVehicleType($vehicle->type)) {
                    $errors[] = "Driver is not authorized for vehicle type: {$vehicle->type}";
                }

                if ($vehicle->vehicle_class && ! $driver->canDriveVehicleClass($vehicle->vehicle_class)) {
                    $errors[] = "Driver is not authorized for vehicle class: {$vehicle->vehicle_class}";
                }

                // Check rental vehicle permission
                if ($vehicle->ownership_type === 'rental' && ! $driver->can_drive_rental) {
                    $errors[] = 'Driver is not authorized to drive rental vehicles';
                }

                // Check client vehicle permission
                if ($vehicle->ownership_type === 'client' && ! $driver->can_drive_client_vehicles) {
                    $errors[] = 'Driver is not authorized to drive client vehicles';
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

    /**
     * Get available drivers for a vehicle
     */
    public function getAvailableDriversForVehicle(string $vehicleId, ?Carbon $date = null): Collection
    {
        $date = $date ?? now();
        $vehicle = Vehicle::find($vehicleId);

        if (! $vehicle) {
            return collect();
        }

        return ApprovedDriver::active()
            ->where(function ($query) use ($vehicle) {
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
            ->with('employee')
            ->get();
    }

    /**
     * Get available vehicles for a driver
     */
    public function getAvailableVehiclesForDriver(string $driverId, ?Carbon $date = null): Collection
    {
        $date = $date ?? now();
        $driver = ApprovedDriver::find($driverId);

        if (! $driver) {
            return collect();
        }

        return Vehicle::active()
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
            ->get();
    }
}
