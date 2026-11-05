<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleAssignment;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Services\ResourceValidationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test comprehensive resource validation for vehicles and drivers
 * before operational assignments and dispatch.
 */
class ResourceValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ResourceValidationService $validationService;

    protected Vehicle $vehicle;

    protected ApprovedDriver $driver;

    protected WarehouseSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
        // Create permissions manually with correct guard
        Permission::create(['name' => 'fleet.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'hr.view', 'guard_name' => 'web']);
        
        $this->user->givePermissionTo(['fleet.view', 'hr.view']);

        $this->validationService = app(ResourceValidationService::class);

        // Create test resources
        $this->site = WarehouseSite::create([
            'code' => 'WH-TEST-001',
            'name' => ['en' => 'Test Warehouse', 'ar' => 'مستودع تجريبي', 'fr' => 'Entrepôt de test'],
            'status' => 'active',
            'country' => 'TN',
        ]);

        // Create department and employee for driver
        $dept = \App\Modules\Hr\Models\Department::create([
            'name' => 'Fleet Department',
            'code' => 'FLEET-TEST'
        ]);

        $empUser = User::factory()->create(['name' => 'Test Driver']);

        $employee = \App\Modules\Hr\Models\Employee::create([
            'first_name' => 'Test',
            'last_name' => 'Driver',
            'work_email' => 'driver@test.local',
            'hire_date' => now()->subYear()->format('Y-m-d'),
            'department_id' => $dept->id,
            'status' => 'active',
            'user_id' => $empUser->id,
        ]);

        $this->vehicle = Vehicle::create([
            'fleet_code' => 'VEH-TEST-001',
            'plate_number' => '123-TEST-456',
            'label' => 'Test Vehicle',
            'status' => 'available',
            'ownership_type' => 'company',
            'type' => 'van',
            'vehicle_class' => 'light',
            'capacity_kg' => 1000,
            'seats' => 3,
            'current_site_id' => $this->site->id,
            'insurance_expiry_date' => now()->addMonths(6)->format('Y-m-d'),
            'registration_expiry_date' => now()->addMonths(12)->format('Y-m-d'),
            'last_service_date' => now()->subMonths(1)->format('Y-m-d'),
            'next_service_date' => now()->addDays(30)->format('Y-m-d'),
            'next_inspection_date' => now()->addDays(60)->format('Y-m-d'),
        ]);

        $this->driver = ApprovedDriver::create([
            'driver_authorization_number' => 'DRV-TEST-001',
            'employee_id' => $employee->id,
            'authorization_status' => 'approved',
            'authorization_date' => now()->subMonths(1)->format('Y-m-d'),
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'license_number' => 'LIC-123456',
            'license_expiry_date' => now()->addYears(2)->format('Y-m-d'),
            'permitted_vehicle_types' => ['van', 'truck', 'sedan'],
            'permitted_vehicle_classes' => ['light', 'medium'],
            'can_drive_rental' => true,
            'can_drive_client_vehicles' => true,
            'max_cargo_kg' => 2000,
            'max_passengers' => 5,
            'driver_score' => 85.00,
            'driver_level' => 'standard',
        ]);

        $this->actingAs($this->user, 'sanctum');
    }

    // ═══════════════════════════════════════════════════════════════
    // Vehicle Status Validation
    // ═══════════════════════════════════════════════════════════════

    public function test_vehicle_under_maintenance_is_invalid(): void
    {
        $this->vehicle->update(['status' => 'under_maintenance']);

        $errors = $this->validationService->validateVehicleAvailability(
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('under_maintenance', implode(' ', $errors));
    }

    public function test_vehicle_out_of_service_is_invalid(): void
    {
        $this->vehicle->update(['status' => 'out_of_service']);

        $errors = $this->validationService->validateVehicleAvailability(
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('out_of_service', implode(' ', $errors));
    }

    public function test_vehicle_retired_is_invalid(): void
    {
        $this->vehicle->update(['status' => 'retired']);

        $errors = $this->validationService->validateVehicleAvailability(
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('retired', implode(' ', $errors));
    }

    // ═══════════════════════════════════════════════════════════════
    // Vehicle Insurance & Registration
    // ═══════════════════════════════════════════════════════════════

    public function test_vehicle_with_expired_insurance_is_invalid(): void
    {
        $this->vehicle->update([
            'insurance_expiry_date' => now()->subDays(10),
        ]);

        $errors = $this->validationService->validateVehicleAvailability(
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('insurance', strtolower(implode(' ', $errors)));
    }

    public function test_vehicle_with_expired_registration_is_invalid(): void
    {
        $this->vehicle->update([
            'registration_expiry_date' => now()->subDays(5),
        ]);

        $errors = $this->validationService->validateVehicleAvailability(
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('registration', strtolower(implode(' ', $errors)));
    }

    // ═══════════════════════════════════════════════════════════════
    // Vehicle Maintenance & Inspection
    // ═══════════════════════════════════════════════════════════════

    public function test_vehicle_overdue_for_maintenance_is_invalid(): void
    {
        $this->vehicle->update([
            'next_service_date' => now()->subDays(10),
        ]);

        $errors = $this->validationService->validateVehicleAvailability(
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('maintenance', strtolower(implode(' ', $errors)));
    }

    public function test_vehicle_overdue_for_inspection_is_invalid(): void
    {
        $this->vehicle->update([
            'next_inspection_date' => now()->subDays(5),
        ]);

        $errors = $this->validationService->validateVehicleAvailability(
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('inspection', strtolower(implode(' ', $errors)));
    }

    // ═══════════════════════════════════════════════════════════════
    // Vehicle Rental & Client Usage
    // ═══════════════════════════════════════════════════════════════

    public function test_rental_vehicle_outside_period_is_invalid(): void
    {
        $this->vehicle->update([
            'ownership_type' => 'rental',
            'rental_status' => 'active',
            'rental_start_date' => now()->addDays(10),
            'rental_end_date' => now()->addDays(30),
        ]);

        $errors = $this->validationService->validateVehicleAvailability(
            $this->vehicle->id,
            now() // Before rental start date
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('period', strtolower(implode(' ', $errors)));
    }

    public function test_client_vehicle_with_inactive_status_is_invalid(): void
    {
        $this->vehicle->update([
            'ownership_type' => 'client',
            'client_vehicle_status' => 'pending_return',
        ]);

        $errors = $this->validationService->validateVehicleAvailability(
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('client', strtolower(implode(' ', $errors)));
    }

    // ═══════════════════════════════════════════════════════════════
    // Vehicle Assignment Conflicts
    // ═══════════════════════════════════════════════════════════════

    public function test_vehicle_with_current_assignment_shows_conflict(): void
    {
        // Create current assignment
        VehicleAssignment::create([
            'vehicle_id' => $this->vehicle->id,
            'custody_type' => 'company_site',
            'site_id' => $this->site->id,
            'start_date' => now()->subDays(5),
            'end_date' => null,
            'is_current' => true,
            'created_by' => $this->user->id,
        ]);

        $conflicts = $this->validationService->checkVehicleAssignmentConflicts(
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($conflicts);
        $this->assertEquals(1, $conflicts->count());
    }

    public function test_vehicle_without_conflicts_passes_validation(): void
    {
        $errors = $this->validationService->validateVehicleAvailability(
            $this->vehicle->id,
            now()
        );

        $this->assertEmpty($errors);
    }

    // ═══════════════════════════════════════════════════════════════
    // Driver Authorization Status
    // ═══════════════════════════════════════════════════════════════

    public function test_driver_with_pending_authorization_is_invalid(): void
    {
        $this->driver->update(['authorization_status' => 'pending']);

        $errors = $this->validationService->validateDriverAuthorization(
            $this->driver->id,
            null,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('pending', strtolower(implode(' ', $errors)));
    }

    public function test_inactive_driver_is_invalid(): void
    {
        $this->driver->update(['is_active' => false]);

        $errors = $this->validationService->validateDriverAuthorization(
            $this->driver->id,
            null,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('inactive', strtolower(implode(' ', $errors)));
    }

    // ═══════════════════════════════════════════════════════════════
    // Driver Suspension
    // ═══════════════════════════════════════════════════════════════

    public function test_suspended_driver_is_invalid(): void
    {
        $this->driver->update([
            'is_suspended' => true,
            'suspended_until' => now()->addDays(30),
        ]);

        $errors = $this->validationService->validateDriverAuthorization(
            $this->driver->id,
            null,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('suspended', strtolower(implode(' ', $errors)));
    }

    public function test_indefinitely_suspended_driver_is_invalid(): void
    {
        $this->driver->update([
            'is_suspended' => true,
            'suspended_until' => null,
        ]);

        $errors = $this->validationService->validateDriverAuthorization(
            $this->driver->id,
            null,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('indefinitely', strtolower(implode(' ', $errors)));
    }

    // ═══════════════════════════════════════════════════════════════
    // Driver Expiry Dates
    // ═══════════════════════════════════════════════════════════════

    public function test_driver_with_expired_authorization_is_invalid(): void
    {
        $this->driver->update([
            'expiry_date' => now()->subDays(10),
        ]);

        $errors = $this->validationService->validateDriverAuthorization(
            $this->driver->id,
            null,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('authorization', strtolower(implode(' ', $errors)));
        $this->assertStringContainsString('expired', strtolower(implode(' ', $errors)));
    }

    public function test_driver_with_expired_license_is_invalid(): void
    {
        $this->driver->update([
            'license_expiry_date' => now()->subMonths(1),
        ]);

        $errors = $this->validationService->validateDriverAuthorization(
            $this->driver->id,
            null,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('license', strtolower(implode(' ', $errors)));
        $this->assertStringContainsString('expired', strtolower(implode(' ', $errors)));
    }

    // ═══════════════════════════════════════════════════════════════
    // Driver Vehicle Type & Class Authorization
    // ═══════════════════════════════════════════════════════════════

    public function test_driver_not_authorized_for_vehicle_type_is_invalid(): void
    {
        $this->driver->update([
            'permitted_vehicle_types' => ['sedan', 'suv'], // Not including 'van'
        ]);

        $errors = $this->validationService->validateDriverAuthorization(
            $this->driver->id,
            $this->vehicle->id, // vehicle is type 'van'
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('type', strtolower(implode(' ', $errors)));
    }

    public function test_driver_not_authorized_for_vehicle_class_is_invalid(): void
    {
        $this->driver->update([
            'permitted_vehicle_classes' => ['heavy'], // Not including 'light'
        ]);

        $errors = $this->validationService->validateDriverAuthorization(
            $this->driver->id,
            $this->vehicle->id, // vehicle is class 'light'
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('class', strtolower(implode(' ', $errors)));
    }

    // ═══════════════════════════════════════════════════════════════
    // Driver Rental & Client Vehicle Permissions
    // ═══════════════════════════════════════════════════════════════

    public function test_driver_cannot_drive_rental_vehicles(): void
    {
        $this->driver->update(['can_drive_rental' => false]);
        $this->vehicle->update([
            'ownership_type' => 'rental',
            'rental_status' => 'active',
        ]);

        $errors = $this->validationService->validateDriverAuthorization(
            $this->driver->id,
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('rental', strtolower(implode(' ', $errors)));
    }

    public function test_driver_cannot_drive_client_vehicles(): void
    {
        $this->driver->update(['can_drive_client_vehicles' => false]);
        $this->vehicle->update([
            'ownership_type' => 'client',
            'client_vehicle_status' => 'active',
        ]);

        $errors = $this->validationService->validateDriverAuthorization(
            $this->driver->id,
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('client', strtolower(implode(' ', $errors)));
    }

    // ═══════════════════════════════════════════════════════════════
    // Driver Cargo & Passenger Restrictions
    // ═══════════════════════════════════════════════════════════════

    public function test_vehicle_exceeds_driver_cargo_limit(): void
    {
        $this->driver->update(['max_cargo_kg' => 500]);
        $this->vehicle->update(['capacity_kg' => 1000]);

        $errors = $this->validationService->validateDriverAuthorization(
            $this->driver->id,
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('cargo', strtolower(implode(' ', $errors)));
    }

    public function test_vehicle_exceeds_driver_passenger_limit(): void
    {
        $this->driver->update(['max_passengers' => 2]);
        $this->vehicle->update(['seats' => 7]);

        $errors = $this->validationService->validateDriverAuthorization(
            $this->driver->id,
            $this->vehicle->id,
            now()
        );

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('passenger', strtolower(implode(' ', $errors)));
    }

    // ═══════════════════════════════════════════════════════════════
    // Compatibility Validation
    // ═══════════════════════════════════════════════════════════════

    public function test_compatible_driver_and_vehicle_pass_validation(): void
    {
        $errors = $this->validationService->validateVehicleDriverCompatibility(
            $this->vehicle->id,
            $this->driver->id
        );

        $this->assertEmpty($errors);
    }

    public function test_incompatible_driver_fails_compatibility_check(): void
    {
        $this->driver->update([
            'max_cargo_kg' => 200, // Much less than vehicle capacity
        ]);

        $errors = $this->validationService->validateVehicleDriverCompatibility(
            $this->vehicle->id,
            $this->driver->id
        );

        $this->assertNotEmpty($errors);
    }

    // ═══════════════════════════════════════════════════════════════
    // Availability Queries
    // ═══════════════════════════════════════════════════════════════

    public function test_get_available_drivers_for_vehicle_returns_authorized_only(): void
    {
        // Create another driver who is suspended
        $suspendedDriver = ApprovedDriver::factory()->create([
            'employee_id' => User::factory()->create()->id,
            'authorization_status' => 'approved',
            'is_active' => true,
            'is_suspended' => true,
            'suspended_until' => now()->addMonths(6),
            'permitted_vehicle_types' => ['van'],
        ]);

        $availableDrivers = $this->validationService->getAvailableDriversForVehicle(
            $this->vehicle->id,
            now()
        );

        $this->assertTrue($availableDrivers->contains($this->driver->id));
        $this->assertFalse($availableDrivers->contains($suspendedDriver->id));
    }

    public function test_get_available_vehicles_for_driver_excludes_expired_insurance(): void
    {
        // Create another vehicle with expired insurance
        $expiredVehicle = Vehicle::factory()->create([
            'fleet_code' => 'VEH-EXPIRED',
            'plate_number' => '999-EXP-999',
            'status' => 'available',
            'type' => 'van',
            'vehicle_class' => 'light',
            'insurance_expiry_date' => now()->subDays(10),
        ]);

        $availableVehicles = $this->validationService->getAvailableVehiclesForDriver(
            $this->driver->id,
            now()
        );

        $this->assertTrue($availableVehicles->contains($this->vehicle->id));
        $this->assertFalse($availableVehicles->contains($expiredVehicle->id));
    }

    // ═══════════════════════════════════════════════════════════════
    // DWO/SWO Resource Validation
    // ═══════════════════════════════════════════════════════════════

    public function test_validate_dwo_resources_with_valid_resources(): void
    {
        $result = $this->validationService->validateDwoResources([
            'vehicle_id' => $this->vehicle->id,
            'approved_driver_id' => $this->driver->id,
        ], now());

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_dwo_resources_with_invalid_vehicle(): void
    {
        $this->vehicle->update(['status' => 'out_of_service']);

        $result = $this->validationService->validateDwoResources([
            'vehicle_id' => $this->vehicle->id,
            'approved_driver_id' => $this->driver->id,
        ], now());

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('vehicle', $result['errors']);
    }

    public function test_validate_swo_resources_with_invalid_driver(): void
    {
        $this->driver->update(['is_suspended' => true]);

        $result = $this->validationService->validateSwoResources([
            'vehicle_id' => $this->vehicle->id,
            'approved_driver_id' => $this->driver->id,
        ], now());

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('driver', $result['errors']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Operational Date Validation
    // ═══════════════════════════════════════════════════════════════

    public function test_validation_uses_future_operational_date(): void
    {
        // Vehicle insurance expires in 5 days
        $this->vehicle->update([
            'insurance_expiry_date' => now()->addDays(5),
        ]);

        // Valid today
        $errors = $this->validationService->validateVehicleAvailability(
            $this->vehicle->id,
            now()
        );
        $this->assertEmpty($errors);

        // Invalid 10 days from now
        $errors = $this->validationService->validateVehicleAvailability(
            $this->vehicle->id,
            now()->addDays(10)
        );
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('insurance', strtolower(implode(' ', $errors)));
    }
}

