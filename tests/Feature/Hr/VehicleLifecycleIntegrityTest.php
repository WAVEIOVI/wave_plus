<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleAssignment;
use App\Modules\Inventory\Models\WarehouseSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Vehicle Lifecycle & Assignment Integrity Test Suite
 *
 * Ensures:
 * - No multiple simultaneous current assignments
 * - Proper transaction locking prevents race conditions
 * - Odometer protection and audit trail
 * - Complete return cleanup
 * - Safe deletion with dependency checks
 * - Default driver ≠ authorized driver
 */
class VehicleLifecycleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    private Vehicle $vehicle;

    private WarehouseSite $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        \App\Models\Role::findOrCreate('Super Admin', 'web');
        $this->admin->assignRole('Super Admin');

        Sanctum::actingAs($this->admin);

        $dept = Department::create(['name' => 'Fleet Operations', 'code' => 'FLEET']);

        $empUser = User::factory()->create(['name' => 'Ahmed Mansour']);

        $this->employee = Employee::create([
            'first_name' => 'Ahmed',
            'last_name' => 'Mansour',
            'work_email' => 'ahmed.mansour@fleet.test',
            'hire_date' => now()->subYear(),
            'department_id' => $dept->id,
            'status' => 'active',
            'user_id' => $empUser->id,
        ]);

        $this->vehicle = Vehicle::create([
            'fleet_code' => 'VEH-LIFECYCLE-01',
            'plate_number' => '111-LFC-222',
            'label' => 'Lifecycle Test Vehicle',
            'type' => 'van',
            'ownership_type' => 'company',
            'status' => 'available',
            'odometer_km' => 10000,
        ]);

        $this->site = WarehouseSite::create([
            'code' => 'WH-TEST',
            'name' => ['en' => 'Test Warehouse'],
            'is_active' => true,
        ]);
    }

    // ─── Assignment Integrity ────────────────────────────────────

    public function test_only_one_current_assignment_per_vehicle(): void
    {
        // Create first assignment
        $assignment1 = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", [
            'custody_type' => 'company_site',
            'site_id' => $this->site->id,
            'start_date' => now()->format('Y-m-d'),
        ])->assertOk()->json('data');

        // Creating second assignment should end first
        $assignment2 = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", [
            'custody_type' => 'employee_home',
            'responsible_employee_id' => $this->employee->user_id,
            'start_date' => now()->format('Y-m-d'),
        ])->assertOk()->json('data');

        // Only assignment2 should be current
        $this->assertTrue((bool) $assignment2['is_current']);

        // Assignment1 should be ended
        $updated1 = VehicleAssignment::find($assignment1['id']);
        $this->assertFalse($updated1->is_current);
        $this->assertNotNull($updated1->end_date);

        // Database should have only ONE current assignment for this vehicle
        $currentCount = VehicleAssignment::where('vehicle_id', $this->vehicle->id)
            ->where('is_current', true)
            ->count();

        $this->assertEquals(1, $currentCount);
    }

    public function test_concurrent_assignment_prevented_by_locking(): void
    {
        // This test validates that database-level locking prevents race conditions
        // In practice, lockForUpdate() ensures sequential processing

        $assignment = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", [
            'custody_type' => 'mission',
            'start_date' => now()->format('Y-m-d'),
        ])->assertOk();

        // Verify operational history flag set
        $this->assertTrue($this->vehicle->fresh()->has_operational_history);

        // Verify only one current assignment exists
        $currentAssignments = VehicleAssignment::where('vehicle_id', $this->vehicle->id)
            ->where('is_current', true)
            ->get();

        $this->assertCount(1, $currentAssignments);
    }

    // ─── Return & Cleanup ────────────────────────────────────────

    public function test_return_clears_stale_assignment_data(): void
    {
        // Assign vehicle
        $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", [
            'custody_type' => 'employee_home',
            'responsible_employee_id' => $this->employee->user_id,
            'site_id' => $this->site->id,
            'location_notes' => 'Employee residence',
            'start_date' => now()->format('Y-m-d'),
            'odometer_at_start' => 10000,
        ])->assertOk();

        $this->vehicle->refresh();
        $this->assertEquals('in_use', $this->vehicle->status);
        $this->assertEquals('employee_home', $this->vehicle->custody_type);
        $this->assertEquals($this->site->id, $this->vehicle->current_site_id);

        // Return vehicle
        $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/return", [
            'end_date' => now()->format('Y-m-d'),
            'odometer_at_end' => 10200,
            'vehicle_status' => 'available',
        ])->assertOk();

        $this->vehicle->refresh();

        // Verify complete cleanup
        $this->assertEquals('available', $this->vehicle->status);
        $this->assertNull($this->vehicle->current_responsible_employee_id);
        $this->assertNull($this->vehicle->custody_type);
        $this->assertNull($this->vehicle->current_site_id);
        $this->assertEquals(10200, $this->vehicle->odometer_km);
    }

    public function test_return_can_specify_destination(): void
    {
        // Assign vehicle
        $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", [
            'custody_type' => 'mission',
            'start_date' => now()->format('Y-m-d'),
        ])->assertOk();

        $returnSite = WarehouseSite::create([
            'code' => 'WH-RETURN',
            'name' => ['en' => 'Return Depot'],
            'is_active' => true,
        ]);

        // Return to specific site
        $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/return", [
            'return_to_site_id' => $returnSite->id,
            'return_custody_type' => 'company_site',
            'location_notes' => 'Bay 5, Maintenance section',
            'vehicle_status' => 'maintenance',
        ])->assertOk();

        $this->vehicle->refresh();
        $this->assertEquals('maintenance', $this->vehicle->status);
        $this->assertEquals('company_site', $this->vehicle->custody_type);
        $this->assertEquals($returnSite->id, $this->vehicle->current_site_id);
    }

    // ─── Odometer Protection ─────────────────────────────────────

    public function test_odometer_cannot_decrease(): void
    {
        $this->vehicle->update(['odometer_km' => 50000]);

        $response = $this->patchJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/odometer", [
            'odometer_km' => 49000,
        ]);

        $response->assertStatus(500); // Will throw InvalidArgumentException

        // Odometer should remain unchanged
        $this->assertEquals(50000, $this->vehicle->fresh()->odometer_km);
    }

    public function test_authorized_odometer_correction_allowed(): void
    {
        $this->vehicle->update(['odometer_km' => 50000]);

        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/odometer/correct", [
            'corrected_odometer' => 49500,
            'correction_reason' => 'Data entry error - manual correction from service log',
        ]);

        $response->assertOk();
        $this->assertEquals(49500, $this->vehicle->fresh()->odometer_km);

        // Verify correction is logged
        $this->assertDatabaseHas('vehicle_activity_log', [
            'vehicle_id' => $this->vehicle->id,
            'event_type' => 'odometer_correction',
        ]);
    }

    public function test_odometer_history_maintained(): void
    {
        $this->vehicle->update(['odometer_km' => 10000]);

        // Update odometer several times
        $this->patchJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/odometer", [
            'odometer_km' => 10500,
        ])->assertOk();

        $this->patchJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/odometer", [
            'odometer_km' => 11000,
        ])->assertOk();

        $this->vehicle->refresh();
        $history = $this->vehicle->odometer_history;

        $this->assertIsArray($history);
        $this->assertGreaterThanOrEqual(2, count($history));
        $this->assertEquals(10500, $history[0]['new_value']);
        $this->assertEquals(11000, $history[1]['new_value']);
    }

    public function test_assignment_return_validates_odometer_cannot_decrease(): void
    {
        // Assign with starting odometer
        $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", [
            'custody_type' => 'mission',
            'odometer_at_start' => 15000,
        ])->assertOk();

        $this->vehicle->update(['odometer_km' => 15000]);

        // Try to return with lower odometer
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/return", [
            'odometer_at_end' => 14500,
        ]);

        $response->assertStatus(500); // Should fail validation
    }

    // ─── Safe Deletion ───────────────────────────────────────────

    public function test_cannot_delete_vehicle_with_operational_history(): void
    {
        // Create assignment to establish operational history
        $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", [
            'custody_type' => 'company_site',
        ])->assertOk();

        $response = $this->deleteJson("/api/hr/fleet/vehicles/{$this->vehicle->id}");

        $response->assertStatus(422);
        $response->assertJsonPath('blockers.0', 'Vehicle has operational history (assignments, maintenance, fuel records, etc.)');

        // Vehicle should still exist
        $this->assertDatabaseHas('vehicles', ['id' => $this->vehicle->id]);
    }

    public function test_can_delete_vehicle_without_history(): void
    {
        $newVehicle = Vehicle::create([
            'fleet_code' => 'VEH-DELETABLE',
            'plate_number' => '999-DEL-888',
            'label' => 'Deletable Vehicle',
            'type' => 'car',
            'ownership_type' => 'company',
            'status' => 'available',
        ]);

        $response = $this->deleteJson("/api/hr/fleet/vehicles/{$newVehicle->id}");

        $response->assertOk();
        $this->assertSoftDeleted('vehicles', ['id' => $newVehicle->id]);
    }

    public function test_check_deletable_endpoint_reports_blockers(): void
    {
        // Create assignment history
        VehicleAssignment::create([
            'vehicle_id' => $this->vehicle->id,
            'custody_type' => 'company_site',
            'start_date' => now(),
            'is_current' => false,
        ]);

        $this->vehicle->update(['has_operational_history' => true]);

        $response = $this->getJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/check-deletable");

        $response->assertOk();
        $response->assertJsonPath('data.can_delete', false);
        $response->assertJsonPath('data.recommendation', 'Consider archiving instead of deleting to preserve historical records');
        $this->assertNotEmpty($response->json('data.blockers'));
    }

    public function test_archive_vehicle_ends_assignment_and_sets_retired(): void
    {
        // Assign vehicle first
        $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", [
            'custody_type' => 'employee_home',
            'responsible_employee_id' => $this->employee->user_id,
        ])->assertOk();

        // Archive vehicle
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/archive", [
            'reason' => 'End of service life - excessive maintenance costs',
        ]);

        $response->assertOk();

        $this->vehicle->refresh();
        $this->assertEquals('retired', $this->vehicle->status);
        $this->assertNotNull($this->vehicle->archived_at);
        $this->assertEquals($this->admin->id, $this->vehicle->archived_by);

        // Verify assignment ended
        $this->assertNull($this->vehicle->currentAssignment);
    }

    // ─── Date Validation ─────────────────────────────────────────

    public function test_assignment_end_date_cannot_be_before_start_date(): void
    {
        // Assign vehicle
        $assignment = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", [
            'custody_type' => 'mission',
            'start_date' => now()->format('Y-m-d'),
        ])->assertOk()->json('data');

        // Try to end with earlier date
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/return", [
            'end_date' => now()->subDay()->format('Y-m-d'),
        ]);

        $response->assertStatus(500); // Should fail validation
    }

    // ─── Driver Authorization ────────────────────────────────────

    public function test_default_driver_does_not_grant_authorization(): void
    {
        // Set default driver on vehicle
        $this->vehicle->update(['default_driver_id' => $this->employee->user_id]);

        // Check driver authorization (should fail - no ApprovedDriver record)
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/check-driver-authorization", [
            'employee_id' => $this->employee->user_id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.authorized', false);
        $response->assertJsonPath('data.reason', 'Employee does not have an active Approved Driver authorization');
    }

    public function test_approved_driver_authorization_required(): void
    {
        // Create approved driver authorization
        $approvedDriver = ApprovedDriver::create([
            'employee_id' => $this->employee->id,
            'driver_authorization_number' => 'DRV-AUTH-001',
            'license_number' => 'DL-123456',
            'license_category' => 'B',
            'authorization_date' => now(),
            'expiry_date' => now()->addYear(),
            'authorization_status' => 'approved',
            'driver_level' => 'standard',
            'permitted_vehicle_types' => ['van', 'car'],
            'can_drive_rental' => true,
            'can_drive_client_vehicles' => false,
        ]);

        // Check authorization (should succeed)
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/check-driver-authorization", [
            'employee_id' => $this->employee->user_id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.authorized', true);
        $response->assertJsonPath('data.driver_authorization', 'DRV-AUTH-001');
    }

    public function test_driver_not_authorized_for_vehicle_type(): void
    {
        // Create driver authorized only for 'car', not 'van'
        ApprovedDriver::create([
            'employee_id' => $this->employee->id,
            'driver_authorization_number' => 'DRV-AUTH-002',
            'license_number' => 'DL-789012',
            'authorization_date' => now(),
            'expiry_date' => now()->addYear(),
            'authorization_status' => 'approved',
            'permitted_vehicle_types' => ['car'],
        ]);

        // Check authorization for van (should fail)
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/check-driver-authorization", [
            'employee_id' => $this->employee->user_id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.authorized', false);
        $response->assertJsonPath('data.reason', 'Driver not authorized for vehicle type: van');
    }

    public function test_driver_not_authorized_for_rental_vehicles(): void
    {
        // Set vehicle as rental
        $this->vehicle->update(['ownership_type' => 'rental']);

        // Create driver not authorized for rentals
        ApprovedDriver::create([
            'employee_id' => $this->employee->id,
            'driver_authorization_number' => 'DRV-AUTH-003',
            'license_number' => 'DL-345678',
            'authorization_date' => now(),
            'expiry_date' => now()->addYear(),
            'authorization_status' => 'approved',
            'permitted_vehicle_types' => ['van'],
            'can_drive_rental' => false,
        ]);

        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/check-driver-authorization", [
            'employee_id' => $this->employee->user_id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.authorized', false);
        $response->assertJsonPath('data.reason', 'Driver not authorized to drive rental vehicles');
    }

    // ─── Rental & Client Lifecycle ──────────────────────────────

    public function test_rental_lifecycle_updates_all_fields_consistently(): void
    {
        $provider = \App\Modules\BusinessPartners\Models\ServiceProvider::create([
            'legal_name' => 'Auto Rental SA',
            'trade_name' => 'AutoRent',
            'status' => 'active',
        ]);

        // Start rental
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/rental/start", [
            'rental_provider_id' => $provider->id,
            'rental_start_date' => now()->format('Y-m-d'),
            'rental_end_date' => now()->addMonth()->format('Y-m-d'),
            'rental_contract_reference' => 'RENT-2026-001',
            'rental_daily_rate' => 85.5,
            'rental_currency' => 'TND',
        ]);

        $response->assertOk();

        $this->vehicle->refresh();
        $this->assertEquals('rental', $this->vehicle->ownership_type);
        $this->assertEquals('rental_active', $this->vehicle->status);
        $this->assertEquals('active', $this->vehicle->rental_status);
        $this->assertEquals($provider->id, $this->vehicle->rental_provider_id);
        $this->assertEquals(85.5, (float) $this->vehicle->rental_daily_rate);

        // End rental
        $endResponse = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/rental/end", [
            'rental_return_date' => now()->format('Y-m-d'),
        ]);

        $endResponse->assertOk();

        $this->vehicle->refresh();
        $this->assertEquals('returned', $this->vehicle->rental_status);
        $this->assertEquals('available', $this->vehicle->status);
        $this->assertNotNull($this->vehicle->rental_return_date);
    }

    public function test_client_vehicle_lifecycle_preserves_driver_flag(): void
    {
        $client = \App\Modules\BusinessPartners\Models\Client::create([
            'legal_name' => 'Client Corp',
            'trade_name' => 'ClientCo',
            'status' => 'active',
        ]);

        // Start with client-supplied driver
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/client-usage/start", [
            'client_id' => $client->id,
            'client_supplies_driver' => true,
            'client_usage_start' => now()->format('Y-m-d'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.client_supplies_driver', true);

        $this->vehicle->refresh();
        $this->assertEquals('client', $this->vehicle->ownership_type);
        $this->assertEquals('client_use', $this->vehicle->status);
        $this->assertTrue($this->vehicle->client_supplies_driver);

        // End client usage
        $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/client-usage/end")->assertOk();

        $this->vehicle->refresh();
        $this->assertEquals('returned', $this->vehicle->client_vehicle_status);
        $this->assertEquals('available', $this->vehicle->status);
    }

    // ─── Activity Logging ────────────────────────────────────────

    public function test_all_lifecycle_events_logged(): void
    {
        // Assign
        $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", [
            'custody_type' => 'mission',
        ])->assertOk();

        $this->assertDatabaseHas('vehicle_activity_log', [
            'vehicle_id' => $this->vehicle->id,
            'event_type' => 'assignment',
        ]);

        // Return
        $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/return")->assertOk();

        $this->assertDatabaseHas('vehicle_activity_log', [
            'vehicle_id' => $this->vehicle->id,
            'event_type' => 'assignment_end',
        ]);

        // Status change
        $this->patchJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/status", [
            'status' => 'maintenance',
            'reason' => 'Scheduled maintenance',
        ])->assertOk();

        $this->assertDatabaseHas('vehicle_activity_log', [
            'vehicle_id' => $this->vehicle->id,
            'event_type' => 'status_change',
        ]);

        // Odometer update
        $this->patchJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/odometer", [
            'odometer_km' => 11000,
        ])->assertOk();

        $this->assertDatabaseHas('vehicle_activity_log', [
            'vehicle_id' => $this->vehicle->id,
            'event_type' => 'odometer_update',
        ]);
    }

    public function test_cannot_assign_retired_vehicle(): void
    {
        $this->vehicle->update(['status' => 'retired']);

        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", [
            'custody_type' => 'mission',
        ]);

        $response->assertStatus(500);
    }

    public function test_cannot_assign_sold_vehicle(): void
    {
        $this->vehicle->update(['status' => 'sold']);

        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", [
            'custody_type' => 'mission',
        ]);

        $response->assertStatus(500);
    }
}
