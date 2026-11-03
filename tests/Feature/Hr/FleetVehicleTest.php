<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Inventory\Models\WarehouseSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetVehicleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        \App\Models\Role::findOrCreate('Super Admin', 'web');
        $this->admin->assignRole('Super Admin');
    }

    public function test_can_list_and_create_vehicles(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'fleet_code' => 'FL-001',
            'plate_number' => '123-TN-4567',
            'label' => 'Service Van 1',
            'make' => 'Toyota',
            'model' => 'HiAce',
            'year' => 2023,
            'type' => 'van',
            'ownership_type' => 'company',
            'status' => 'available',
            'capacity_kg' => 1200,
            'odometer_km' => 15000,
        ];

        $response = $this->postJson('/api/hr/fleet/vehicles', $payload);
        $response->assertCreated();
        $response->assertJsonPath('data.fleet_code', 'FL-001');
        $response->assertJsonPath('data.plate_number', '123-TN-4567');

        $list = $this->getJson('/api/hr/fleet/vehicles');
        $list->assertOk();
        $list->assertJsonCount(1, 'data');
    }

    public function test_unrelated_hr_permission_cannot_mutate_fleet(): void
    {
        $role = \App\Models\Role::findOrCreate('Recruitment Manager', 'web');
        \App\Models\Permission::findOrCreate('hr.recruitment.manage', 'web');
        $role->givePermissionTo('hr.recruitment.manage');

        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->postJson('/api/hr/fleet/vehicles', [
            'fleet_code' => 'FL-DENY-01',
            'plate_number' => 'DENY-01',
            'label' => 'Should not be created',
            'type' => 'van',
            'ownership_type' => 'company',
        ])->assertForbidden();

        $this->assertDatabaseMissing((new Vehicle)->getTable(), ['fleet_code' => 'FL-DENY-01']);
    }

    public function test_read_people_skills_permission_does_not_authorize_fleet_mutations(): void
    {
        $role = \App\Models\Role::findOrCreate('People Skills Viewer', 'web');
        \App\Models\Permission::findOrCreate('read-PeopleSkills', 'web');
        $role->givePermissionTo('read-PeopleSkills');

        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->getJson('/api/hr/fleet/vehicles')->assertOk();
        $this->postJson('/api/hr/fleet/vehicles', [
            'fleet_code' => 'FL-READ-ONLY',
            'plate_number' => 'READ-01',
            'label' => 'Read-only actor',
            'type' => 'van',
            'ownership_type' => 'company',
        ])->assertForbidden();

        $this->assertDatabaseMissing((new Vehicle)->getTable(), ['fleet_code' => 'FL-READ-ONLY']);
    }

    public function test_employee_edit_permission_does_not_authorize_fleet_mutations(): void
    {
        $role = \App\Models\Role::findOrCreate('Employee Manager', 'web');
        \App\Models\Permission::findOrCreate('hr.employees.edit', 'web');
        $role->givePermissionTo('hr.employees.edit');

        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->getJson('/api/hr/fleet/vehicles')->assertForbidden();

        $this->postJson('/api/hr/fleet/vehicles', [
            'fleet_code' => 'FL-EMP-01',
            'plate_number' => 'EMP-01',
            'label' => 'Employee permission must not grant Fleet access',
            'type' => 'van',
            'ownership_type' => 'company',
        ])->assertForbidden();

        $this->assertDatabaseMissing((new Vehicle)->getTable(), ['fleet_code' => 'FL-EMP-01']);
    }

    public function test_hr_permissions_map_to_scoped_frontend_abilities(): void
    {
        $role = \App\Models\Role::findOrCreate('Fleet Manager', 'web');
        foreach (['hr.fleet.view', 'hr.fleet.manage'] as $permission) {
            \App\Models\Permission::findOrCreate($permission, 'web');
        }
        $role->givePermissionTo(['hr.fleet.view', 'hr.fleet.manage']);

        $user = User::factory()->create([
            'email' => 'fleet-manager@example.com',
            'password' => 'password',
        ]);
        $user->assignRole($role);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'fleet-manager@example.com',
            'password' => 'password',
        ])->assertOk();

        $response
            ->assertJsonFragment(['action' => 'read', 'subject' => 'Fleet'])
            ->assertJsonFragment(['action' => 'create', 'subject' => 'Fleet'])
            ->assertJsonFragment(['action' => 'update', 'subject' => 'Fleet']);

        $this->assertNotContains(
            ['action' => 'create', 'subject' => 'PeopleSkills'],
            $response->json('userAbilityRules')
        );
    }

    public function test_fleet_view_permission_allows_reads_but_not_mutations(): void
    {
        $role = \App\Models\Role::findOrCreate('Fleet Viewer', 'web');
        \App\Models\Permission::findOrCreate('hr.fleet.view', 'web');
        $role->givePermissionTo('hr.fleet.view');

        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->getJson('/api/hr/fleet/vehicles')->assertOk();

        $this->postJson('/api/hr/fleet/vehicles', [
            'fleet_code' => 'FL-VIEW-ONLY',
            'plate_number' => 'VIEW-01',
            'label' => 'Read-only actor',
            'type' => 'van',
            'ownership_type' => 'company',
        ])->assertForbidden();

        $this->assertDatabaseMissing((new Vehicle)->getTable(), ['fleet_code' => 'FL-VIEW-ONLY']);
    }

    public function test_fleet_manage_permission_allows_vehicle_workflows(): void
    {
        $role = \App\Models\Role::findOrCreate('Fleet Operator', 'web');
        \App\Models\Permission::findOrCreate('hr.fleet.manage', 'web');
        $role->givePermissionTo('hr.fleet.manage');

        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->postJson('/api/hr/fleet/vehicles', [
            'fleet_code' => 'FL-MANAGED-01',
            'plate_number' => 'MANAGED-01',
            'label' => 'Fleet manager vehicle',
            'type' => 'van',
            'ownership_type' => 'company',
        ])->assertCreated();

        $this->assertDatabaseHas((new Vehicle)->getTable(), ['fleet_code' => 'FL-MANAGED-01']);
    }

    public function test_leave_approval_permission_does_not_authorize_leave_type_changes(): void
    {
        $role = \App\Models\Role::findOrCreate('Leave Approver', 'web');
        \App\Models\Permission::findOrCreate('hr.leave.approve', 'web');
        $role->givePermissionTo('hr.leave.approve');

        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->postJson('/api/hr/leave-types', [])->assertForbidden();
    }

    public function test_vehicle_lifecycle_assignment_and_return(): void
    {
        Sanctum::actingAs($this->admin);

        $vehicle = Vehicle::create([
            'fleet_code' => 'FL-002',
            'plate_number' => '888-TN-9999',
            'label' => 'Courier Truck',
            'type' => 'truck',
            'ownership_type' => 'company',
            'status' => 'available',
            'odometer_km' => 20000,
        ]);

        $employee = User::factory()->create();

        // Assign vehicle
        $assignResponse = $this->postJson("/api/hr/fleet/vehicles/{$vehicle->id}/assign", [
            'responsible_employee_id' => $employee->id,
            'custody_type' => 'mission',
            'reason' => 'Northern delivery run',
            'odometer_at_start' => 20000,
        ]);
        $assignResponse->assertOk();

        $vehicle->refresh();
        $this->assertEquals('in_use', $vehicle->status);
        $this->assertNotNull($vehicle->currentAssignment);
        $this->assertEquals($employee->id, $vehicle->currentAssignment->responsible_employee_id);

        // Return vehicle
        $returnResponse = $this->postJson("/api/hr/fleet/vehicles/{$vehicle->id}/return", [
            'odometer_at_end' => 20450,
            'vehicle_status' => 'available',
        ]);
        $returnResponse->assertOk();

        $vehicle->refresh();
        $this->assertEquals('available', $vehicle->status);
        $this->assertEquals(20450, $vehicle->odometer_km);
        $this->assertNull($vehicle->currentAssignment);
    }

    public function test_rental_lifecycle(): void
    {
        Sanctum::actingAs($this->admin);

        $vehicle = Vehicle::create([
            'fleet_code' => 'FL-RENT-1',
            'plate_number' => '444-TN-5555',
            'label' => 'Rental SUV',
            'type' => 'car',
            'ownership_type' => 'rental',
            'status' => 'available',
        ]);

        $provider = ServiceProvider::create([
            'legal_name' => 'AutoRent SARL',
            'trade_name' => 'AutoRent',
            'status' => 'active',
        ]);

        // Start rental
        $start = $this->postJson("/api/hr/fleet/vehicles/{$vehicle->id}/rental/start", [
            'rental_provider_id' => $provider->id,
            'rental_start_date' => now()->toDateString(),
            'rental_end_date' => now()->addMonths(1)->toDateString(),
            'rental_daily_rate' => 120.00,
            'rental_currency' => 'TND',
        ]);
        $start->assertOk();

        $vehicle->refresh();
        $this->assertEquals('rental_active', $vehicle->status);

        // End rental
        $end = $this->postJson("/api/hr/fleet/vehicles/{$vehicle->id}/rental/end");
        $end->assertOk();

        $vehicle->refresh();
        $this->assertEquals('available', $vehicle->status);
    }

    public function test_client_usage_lifecycle(): void
    {
        Sanctum::actingAs($this->admin);

        $vehicle = Vehicle::create([
            'fleet_code' => 'FL-CLI-1',
            'plate_number' => '777-TN-8888',
            'label' => 'Client Dedicated Van',
            'type' => 'van',
            'ownership_type' => 'client',
            'status' => 'available',
        ]);

        $client = Client::create([
            'legal_name' => 'Sonatrach Logistics',
            'trade_name' => 'Sonatrach',
            'status' => 'active',
        ]);

        // Start client usage
        $start = $this->postJson("/api/hr/fleet/vehicles/{$vehicle->id}/client-usage/start", [
            'client_id' => $client->id,
            'client_usage_start' => now()->toDateString(),
        ]);
        $start->assertOk();

        $vehicle->refresh();
        $this->assertEquals('client_use', $vehicle->status);

        // End client usage
        $end = $this->postJson("/api/hr/fleet/vehicles/{$vehicle->id}/client-usage/end");
        $end->assertOk();

        $vehicle->refresh();
        $this->assertEquals('available', $vehicle->status);
    }

    public function test_child_document_crud(): void
    {
        Sanctum::actingAs($this->admin);

        $vehicle = Vehicle::create([
            'plate_number' => '111-DOC-222',
            'label' => 'Doc Test Vehicle',
            'type' => 'car',
            'ownership_type' => 'company',
            'status' => 'available',
        ]);

        $createDoc = $this->postJson("/api/hr/fleet/vehicles/{$vehicle->id}/documents", [
            'document_type' => 'insurance',
            'title' => 'Annual Comprehensive Insurance',
            'document_number' => 'INS-990022',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'status' => 'active',
        ]);
        $createDoc->assertCreated();
        $docId = $createDoc->json('data.id');

        $listDocs = $this->getJson("/api/hr/fleet/vehicles/{$vehicle->id}/documents");
        $listDocs->assertOk();
        $listDocs->assertJsonCount(1, 'data');

        $updateDoc = $this->putJson("/api/hr/fleet/documents/{$docId}", [
            'notes' => 'Updated policy note',
        ]);
        $updateDoc->assertOk();
        $this->assertEquals('Updated policy note', $updateDoc->json('data.notes'));

        $deleteDoc = $this->deleteJson("/api/hr/fleet/documents/{$docId}");
        $deleteDoc->assertOk();
    }

    public function test_child_fuel_record_crud(): void
    {
        Sanctum::actingAs($this->admin);

        $vehicle = Vehicle::create([
            'plate_number' => '333-FUEL-444',
            'label' => 'Fuel Test Vehicle',
            'type' => 'truck',
            'ownership_type' => 'company',
            'status' => 'available',
            'odometer_km' => 10000,
        ]);

        $createFuel = $this->postJson("/api/hr/fleet/vehicles/{$vehicle->id}/fuel-records", [
            'fill_date' => now()->toDateString(),
            'fuel_type' => 'diesel',
            'quantity' => 60.5,
            'odometer_km' => 10500,
            'unit_price' => 2.15,
            'total_cost' => 130.075,
            'is_full_tank' => true,
        ]);
        $createFuel->assertCreated();
        $fuelId = $createFuel->json('data.id');

        $vehicle->refresh();
        $this->assertEquals(10500, $vehicle->odometer_km);

        $getFuel = $this->getJson("/api/hr/fleet/fuel-records/{$fuelId}");
        $getFuel->assertOk();
        $this->assertEquals(60.5, $getFuel->json('data.quantity'));
    }

    public function test_child_maintenance_record_crud(): void
    {
        Sanctum::actingAs($this->admin);

        $vehicle = Vehicle::create([
            'plate_number' => '555-MAINT-666',
            'label' => 'Maint Test Vehicle',
            'type' => 'van',
            'ownership_type' => 'company',
            'status' => 'available',
        ]);

        $createMaint = $this->postJson("/api/hr/fleet/vehicles/{$vehicle->id}/maintenance-records", [
            'maintenance_type' => 'preventive',
            'title' => 'Scheduled 20k Oil Change',
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'status' => 'scheduled',
        ]);
        $createMaint->assertCreated();
        $maintId = $createMaint->json('data.id');
        $this->assertNotEmpty($createMaint->json('data.maintenance_number'));

        $updateMaint = $this->putJson("/api/hr/fleet/maintenance-records/{$maintId}", [
            'status' => 'completed',
            'completed_date' => now()->toDateString(),
            'total_cost' => 350.0,
            'next_service_date' => now()->addMonths(6)->toDateString(),
        ]);
        $updateMaint->assertOk();

        $vehicle->refresh();
        $this->assertEquals(now()->addMonths(6)->toDateString(), $vehicle->next_service_date->toDateString());
    }

    public function test_child_checklist_crud(): void
    {
        Sanctum::actingAs($this->admin);

        $vehicle = Vehicle::create([
            'plate_number' => '777-CHK-888',
            'label' => 'Checklist Test Vehicle',
            'type' => 'van',
            'ownership_type' => 'company',
            'status' => 'available',
        ]);

        $createChecklist = $this->postJson("/api/hr/fleet/vehicles/{$vehicle->id}/checklists", [
            'checklist_type' => 'pre_trip',
            'title' => 'Pre-trip Daily Inspection',
            'overall_status' => 'passed',
            'items_passed' => 10,
            'items_failed' => 0,
            'items_total' => 10,
            'has_issues' => false,
        ]);
        $createChecklist->assertCreated();
        $checklistId = $createChecklist->json('data.id');

        $getChecklist = $this->getJson("/api/hr/fleet/checklists/{$checklistId}");
        $getChecklist->assertOk();
        $this->assertEquals(100.0, $getChecklist->json('data.pass_rate'));
    }

    public function test_logistics_delegates_to_hr_fleet_controller(): void
    {
        Sanctum::actingAs($this->admin);

        $vehicle = Vehicle::create([
            'plate_number' => 'LOG-DELEGATE-1',
            'label' => 'Logistics Shared Vehicle',
            'type' => 'truck',
            'ownership_type' => 'company',
            'status' => 'available',
        ]);

        $response = $this->getJson("/api/logistics/vehicles/{$vehicle->id}");
        $response->assertOk();
        $response->assertJsonPath('data.plate_number', 'LOG-DELEGATE-1');
    }

    public function test_vehicle_form_fields_persist(): void
    {
        Sanctum::actingAs($this->admin);

        $site = WarehouseSite::create(['code' => 'WH-TEST', 'name' => ['en' => 'Test Site'], 'status' => 'active']);
        $driver = User::factory()->create();

        $payload = [
            'plate_number' => '777-FLD-999',
            'label' => 'Field Coverage Vehicle',
            'type' => 'van',
            'ownership_type' => 'company',
            'registration_number' => 'REG-777999',
            'registration_date' => now()->subYear()->toDateString(),
            'color' => 'White',
            'fuel_tank_capacity' => 80.5,
            'battery_capacity_kwh' => null,
            'last_inspection_date' => now()->subMonths(2)->toDateString(),
            'last_service_date' => now()->subMonths(3)->toDateString(),
            'default_driver_id' => $driver->id,
            'current_site_id' => $site->id,
            'current_location_notes' => 'Bay 3, North yard',
        ];

        $response = $this->postJson('/api/hr/fleet/vehicles', $payload);
        $response->assertCreated();

        $id = $response->json('data.id');

        $this->assertDatabaseHas('vehicles', [
            'id' => $id,
            'registration_number' => 'REG-777999',
            'color' => 'White',
            'default_driver_id' => $driver->id,
            'current_site_id' => $site->id,
        ]);

        $show = $this->getJson("/api/hr/fleet/vehicles/{$id}");
        $show->assertOk();
        $show->assertJsonPath('data.registration_number', 'REG-777999');
        $show->assertJsonPath('data.color', 'White');
    }

    public function test_validation_rejects_invalid_vehicle_payload(): void
    {
        Sanctum::actingAs($this->admin);

        // Duplicate plate number across vehicles.
        Vehicle::create([
            'plate_number' => 'DUP-111',
            'label' => 'Existing',
            'type' => 'van',
            'ownership_type' => 'company',
        ]);

        $this->postJson('/api/hr/fleet/vehicles', [
            'plate_number' => 'DUP-111',
            'label' => 'Duplicate Plate',
            'type' => 'van',
            'ownership_type' => 'bogus',
        ])->assertStatus(422);
    }

    public function test_mutations_require_permission(): void
    {
        $plainUser = User::factory()->create();
        Sanctum::actingAs($plainUser);

        $this->postJson('/api/hr/fleet/vehicles', [
            'plate_number' => 'NOPERM-1',
            'label' => 'No Permission Vehicle',
            'type' => 'van',
            'ownership_type' => 'company',
        ])->assertStatus(403);
    }

    public function test_reading_logistics_vehicles_still_requires_auth(): void
    {
        $this->getJson('/api/logistics/vehicles')->assertStatus(401);
    }
}
