<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Inventory\Models\WarehouseSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovedDriverAndFleetEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

    private Employee $employee;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles & permissions
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Fleet Manager', 'web');
        Role::findOrCreate('Fleet Viewer', 'web');

        Permission::findOrCreate('hr.fleet.view', 'web');
        Permission::findOrCreate('hr.fleet.manage', 'web');

        $fleetManagerRole = Role::findByName('Fleet Manager', 'web');
        $fleetManagerRole->givePermissionTo(['hr.fleet.view', 'hr.fleet.manage']);

        $fleetViewerRole = Role::findByName('Fleet Viewer', 'web');
        $fleetViewerRole->givePermissionTo(['hr.fleet.view']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole('Fleet Viewer');

        $dept = Department::create([
            'name' => 'Logistics & Fleet Operations',
            'code' => 'OPS-FLEET',
        ]);

        $empUser = User::factory()->create([
            'name' => 'Firas Ben Salem',
            'email' => 'firas.bensalem@waveplus.local',
        ]);

        $this->employee = Employee::create([
            'first_name' => 'Firas',
            'last_name' => 'Ben Salem',
            'work_email' => 'firas.bensalem@waveplus.local',
            'hire_date' => '2025-01-01',
            'department_id' => $dept->id,
            'status' => 'active',
            'user_id' => $empUser->id,
        ]);

        $this->vehicle = Vehicle::create([
            'fleet_code' => 'FL-TEST-99',
            'plate_number' => '199-TN-9999',
            'label' => 'Primary Delivery Van',
            'make' => 'Peugeot',
            'model' => 'Boxer',
            'year' => 2024,
            'type' => 'van',
            'ownership_type' => 'company',
            'status' => 'available',
            'odometer_km' => 25000,
        ]);
    }

    public function test_can_update_vehicle_telemetry_and_records_activity(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'last_gps_latitude' => 36.8064948,
            'last_gps_longitude' => 10.1815316,
            'last_location_address' => 'Zone Industrielle Charguia II, Tunis',
            'last_speed_kmh' => 65,
            'last_gps_timestamp' => now()->toIso8601String(),
        ];

        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/telemetry", $payload);

        $response->assertOk();
        $response->assertJsonPath('data.last_gps_latitude', 36.8064948);
        $response->assertJsonPath('data.last_gps_longitude', 10.1815316);
        $response->assertJsonPath('data.last_location_address', 'Zone Industrielle Charguia II, Tunis');
        $response->assertJsonPath('data.last_speed_kmh', 65);

        $this->assertDatabaseHas('vehicles', [
            'id' => $this->vehicle->id,
            'last_location_address' => 'Zone Industrielle Charguia II, Tunis',
            'last_speed_kmh' => 65,
        ]);

        $this->assertDatabaseHas('vehicle_activity_log', [
            'vehicle_id' => $this->vehicle->id,
            'event_type' => 'location_change',
        ]);
    }

    public function test_telemetry_mutation_requires_fleet_manage_permission(): void
    {
        Sanctum::actingAs($this->viewer);

        $payload = [
            'last_gps_latitude' => 36.8065,
            'last_gps_longitude' => 10.1815,
        ];

        $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/telemetry", $payload)
            ->assertForbidden();
    }

    public function test_telemetry_mutation_requires_authentication(): void
    {
        $payload = [
            'last_gps_latitude' => 36.8065,
            'last_gps_longitude' => 10.1815,
        ];

        $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/telemetry", $payload)
            ->assertUnauthorized();
    }

    public function test_vehicle_custody_assignment_to_employee_home_and_company_site(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Assign to employee home
        $assignHomePayload = [
            'custody_type' => 'employee_home',
            'responsible_employee_id' => $this->employee->user_id,
            'start_date' => now()->format('Y-m-d'),
            'odometer_at_start' => 25000,
            'location_notes' => 'Driver Residence, Ariana',
            'reason' => 'On-call emergency response assignment',
        ];

        $assignResp = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", $assignHomePayload);
        $assignResp->assertOk();
        $assignResp->assertJsonPath('data.custody_type', 'employee_home');
        $assignResp->assertJsonPath('data.responsible_employee_id', $this->employee->user_id);
        $this->assertSame('in_use', $this->vehicle->fresh()->status);
        $this->assertSame('employee_home', $this->vehicle->fresh()->custody_type);

        // 2. Return vehicle
        $returnPayload = [
            'end_date' => now()->format('Y-m-d'),
            'odometer_at_end' => 25300,
            'vehicle_status' => 'available',
        ];

        $returnResp = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/return", $returnPayload);
        $returnResp->assertOk();
        $this->assertSame('available', $this->vehicle->fresh()->status);

        // 3. Assign to company warehouse site
        $site = WarehouseSite::create([
            'code' => 'WH-TUN-01',
            'name' => ['en' => 'Tunis Central Hub', 'fr' => 'Hub Central Tunis'],
            'address' => 'Port de Rades',
            'is_active' => true,
        ]);

        $assignSitePayload = [
            'custody_type' => 'company_site',
            'site_id' => $site->id,
            'start_date' => now()->format('Y-m-d'),
            'odometer_at_start' => 25300,
            'reason' => 'Warehouse pool vehicle',
        ];

        $assignSiteResp = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", $assignSitePayload);
        $assignSiteResp->assertOk();
        $assignSiteResp->assertJsonPath('data.custody_type', 'company_site');
        $assignSiteResp->assertJsonPath('data.site_id', $site->id);
        $this->assertSame('in_use', $this->vehicle->fresh()->status);
        $this->assertSame('company_site', $this->vehicle->fresh()->custody_type);
    }

    public function test_client_vehicle_usage_with_and_without_driver(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'legal_name' => 'Pharma Distribution SA',
            'trade_name' => 'PharmaDist',
            'status' => 'active',
        ]);

        // Start client usage: company provides vehicle, client supplies own driver
        $clientUsagePayload = [
            'client_id' => $client->id,
            'client_supplies_driver' => true,
            'client_usage_start' => now()->format('Y-m-d'),
        ];

        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/client-usage/start", $clientUsagePayload);
        $response->assertOk();
        $response->assertJsonPath('data.status', 'client_use');
        $response->assertJsonPath('data.client_supplies_driver', true);
        $response->assertJsonPath('data.client_id', $client->id);

        // End client usage
        $endResp = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/client-usage/end");
        $endResp->assertOk();
        $endResp->assertJsonPath('data.status', 'available');

        // Start client usage: internal company driver assigned
        $clientUsageInternalDriver = [
            'client_id' => $client->id,
            'client_supplies_driver' => false,
            'client_usage_start' => now()->format('Y-m-d'),
        ];

        $response2 = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/client-usage/start", $clientUsageInternalDriver);
        $response2->assertOk();
        $response2->assertJsonPath('data.status', 'client_use');
        $response2->assertJsonPath('data.client_supplies_driver', false);
    }

    public function test_approved_driver_lifecycle(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Authorize a driver
        $storePayload = [
            'employee_id' => $this->employee->id,
            'license_number' => 'DL-TUN-987654',
            'license_category' => 'B',
            'license_expiry_date' => now()->addYears(3)->format('Y-m-d'),
            'authorization_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'driver_level' => 'standard',
            'permitted_vehicle_types' => ['van', 'car'],
            'notes' => 'Authorized for light commercial vehicles',
        ];

        $createResp = $this->postJson('/api/hr/approved-drivers', $storePayload);
        $createResp->assertCreated();
        $driverId = $createResp->json('data.id');

        $this->assertNotNull($driverId);
        $createResp->assertJsonPath('data.authorization_status', 'approved');
        $createResp->assertJsonPath('data.driver_score', 100);
        $createResp->assertJsonPath('data.license_number', 'DL-TUN-987654');

        // 2. Fetch driver detail
        $showResp = $this->getJson("/api/hr/approved-drivers/{$driverId}");
        $showResp->assertOk();
        $showResp->assertJsonPath('data.id', $driverId);

        // 3. Renew driver license / authorization
        $renewPayload = [
            'expiry_date' => now()->addYears(2)->format('Y-m-d'),
            'license_expiry_date' => now()->addYears(5)->format('Y-m-d'),
        ];

        $renewResp = $this->postJson("/api/hr/approved-drivers/{$driverId}/renew", $renewPayload);
        $renewResp->assertOk();
        $renewResp->assertJsonPath('data.authorization_status', 'approved');

        // 4. Suspend driver
        $suspendPayload = [
            'suspended_until' => now()->addMonth()->format('Y-m-d'),
            'suspension_reason' => 'Temporary medical suspension following eye surgery',
        ];

        $suspendResp = $this->postJson("/api/hr/approved-drivers/{$driverId}/suspend", $suspendPayload);
        $suspendResp->assertOk();
        $suspendResp->assertJsonPath('data.authorization_status', 'suspended');

        // 5. Reinstate driver
        $reinstateResp = $this->postJson("/api/hr/approved-drivers/{$driverId}/reinstate", [
            'notes' => 'Medical clearance certificate submitted',
        ]);
        $reinstateResp->assertOk();
        $reinstateResp->assertJsonPath('data.authorization_status', 'approved');

        // 6. Revoke driver
        $revokeResp = $this->postJson("/api/hr/approved-drivers/{$driverId}/revoke", [
            'reason' => 'Driver transitioned to permanent desk role',
        ]);
        $revokeResp->assertOk();
        $revokeResp->assertJsonPath('data.authorization_status', 'revoked');
    }

    public function test_driver_incident_recording_and_score_recalculation(): void
    {
        Sanctum::actingAs($this->admin);

        // Create driver
        $driver = ApprovedDriver::create([
            'employee_id' => $this->employee->id,
            'driver_authorization_number' => 'AUTH-2026-0001',
            'license_number' => 'DL-TUN-112233',
            'license_category' => 'B',
            'authorization_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'authorization_status' => 'approved',
            'driver_score' => 100,
            'driver_level' => 'standard',
            'approved_by' => $this->admin->id,
        ]);

        // Record incident
        $incidentPayload = [
            'vehicle_id' => $this->vehicle->id,
            'incident_type' => 'speeding',
            'severity' => 'minor',
            'incident_date' => now()->format('Y-m-d'),
            'title' => 'Speeding camera ticket on Route GP1',
            'description' => 'Exceeded 70 km/h limit by 12 km/h in suburban zone',
            'points_deducted' => 5,
        ];

        $incidentResp = $this->postJson("/api/hr/approved-drivers/{$driver->id}/incidents", $incidentPayload);
        $incidentResp->assertCreated();

        $this->assertDatabaseHas('driver_incidents', [
            'driver_id' => $driver->id,
            'incident_type' => 'speeding',
            'severity' => 'minor',
        ]);

        // Verify score recalculation / adjustment
        $adjustResp = $this->postJson("/api/hr/approved-drivers/{$driver->id}/adjust-score", [
            'score_change' => -5,
            'reason' => 'Annual safe driving adjustment',
        ]);
        $adjustResp->assertOk();
        $adjustResp->assertJsonPath('data.driver_score', 93);
    }

    public function test_approved_driver_authorization_restrictions(): void
    {
        // 1. Viewer can read driver list
        Sanctum::actingAs($this->viewer);

        $driver = ApprovedDriver::create([
            'employee_id' => $this->employee->id,
            'driver_authorization_number' => 'AUTH-VIEW-01',
            'license_number' => 'DL-TUN-445566',
            'authorization_date' => now()->format('Y-m-d'),
            'authorization_status' => 'approved',
            'driver_score' => 100,
        ]);

        $this->getJson('/api/hr/approved-drivers')->assertOk();
        $this->getJson("/api/hr/approved-drivers/{$driver->id}")->assertOk();

        // 2. Viewer cannot create driver
        $this->postJson('/api/hr/approved-drivers', [
            'employee_id' => $this->employee->id,
            'license_number' => 'DL-FAIL-01',
        ])->assertForbidden();

        // 3. Viewer cannot suspend driver
        $this->postJson("/api/hr/approved-drivers/{$driver->id}/suspend", [
            'suspension_reason' => 'Not allowed',
        ])->assertForbidden();

        // 4. Viewer cannot delete driver
        $this->deleteJson("/api/hr/approved-drivers/{$driver->id}")->assertForbidden();
    }
}
