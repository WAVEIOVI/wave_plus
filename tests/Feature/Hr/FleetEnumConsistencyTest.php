<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleAssignment;
use App\Modules\Hr\Models\VehicleChecklist;
use App\Modules\Hr\Models\VehicleDocument;
use App\Modules\Hr\Models\VehicleFuelRecord;
use App\Modules\Hr\Models\VehicleMaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fleet ENUM Consistency Test Suite
 *
 * Ensures all ENUM values work consistently across:
 * - Database migrations
 * - Controller validation
 * - Frontend options
 */
class FleetEnumConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        \App\Models\Role::findOrCreate('Super Admin', 'web');
        $this->admin->assignRole('Super Admin');

        Sanctum::actingAs($this->admin);

        $this->vehicle = Vehicle::create([
            'plate_number' => 'TEST-ENUM-001',
            'label' => 'ENUM Test Vehicle',
            'type' => 'van',
            'ownership_type' => 'company',
            'status' => 'available',
        ]);
    }

    // ─── Vehicle Custody Types ───────────────────────────────────

    public function test_vehicle_custody_types_include_storage_and_other(): void
    {
        // Test 'storage' custody type
        $payload = [
            'responsible_employee_id' => null,
            'custody_type' => 'storage',
            'start_date' => now()->toDateString(),
            'reason' => 'Long-term storage',
        ];

        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", $payload);
        $response->assertOk();

        $this->vehicle->refresh();
        $this->assertEquals('storage', $this->vehicle->custody_type);

        // Test 'other' custody type
        $payload['custody_type'] = 'other';
        $payload['reason'] = 'Special custody arrangement';

        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/assign", $payload);
        $response->assertOk();

        $this->vehicle->refresh();
        $this->assertEquals('other', $this->vehicle->custody_type);
    }

    public function test_vehicle_assignment_supports_all_custody_types(): void
    {
        $custodyTypes = [
            'company_site',
            'employee_home',
            'mission',
            'maintenance',
            'rental',
            'client',
            'storage',
            'other',
        ];

        foreach ($custodyTypes as $type) {
            $assignment = VehicleAssignment::create([
                'vehicle_id' => $this->vehicle->id,
                'custody_type' => $type,
                'start_date' => now(),
                'is_current' => false,
            ]);

            $this->assertEquals($type, $assignment->custody_type);
        }

        $this->assertCount(count($custodyTypes), $this->vehicle->assignments);
    }

    // ─── Maintenance Types ───────────────────────────────────────

    public function test_maintenance_record_supports_all_types(): void
    {
        $maintenanceTypes = [
            'preventive',
            'corrective',
            'inspection',
            'repair',
            'overhaul',
            'warranty',
            'recall',
            'other',
        ];

        foreach ($maintenanceTypes as $type) {
            $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/maintenance-records", [
                'maintenance_type' => $type,
                'title' => "Test {$type} maintenance",
                'scheduled_date' => now()->toDateString(),
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.maintenance_type', $type);
        }

        $this->assertCount(count($maintenanceTypes), $this->vehicle->maintenanceRecords);
    }

    // ─── Maintenance Priorities ──────────────────────────────────

    public function test_maintenance_record_supports_all_priorities(): void
    {
        $priorities = ['low', 'normal', 'medium', 'high', 'urgent', 'critical'];

        foreach ($priorities as $priority) {
            $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/maintenance-records", [
                'maintenance_type' => 'preventive',
                'priority' => $priority,
                'title' => "Test {$priority} priority maintenance",
                'scheduled_date' => now()->toDateString(),
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.priority', $priority);
        }

        $this->assertCount(count($priorities), $this->vehicle->maintenanceRecords);
    }

    // ─── Maintenance Status ──────────────────────────────────────

    public function test_maintenance_record_does_not_support_on_hold(): void
    {
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/maintenance-records", [
            'maintenance_type' => 'preventive',
            'title' => 'Test on_hold status',
            'scheduled_date' => now()->toDateString(),
            'status' => 'on_hold',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    public function test_maintenance_record_supports_valid_statuses(): void
    {
        $validStatuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];

        foreach ($validStatuses as $status) {
            $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/maintenance-records", [
                'maintenance_type' => 'preventive',
                'title' => "Test {$status} status",
                'scheduled_date' => now()->toDateString(),
                'status' => $status,
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.status', $status);
        }
    }

    // ─── Document Status ─────────────────────────────────────────

    public function test_document_status_uses_pending_renewal_not_expiring_soon(): void
    {
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/documents", [
            'document_type' => 'insurance',
            'title' => 'Test document',
            'status' => 'pending_renewal',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending_renewal');

        // Test that 'expiring_soon' is not accepted
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/documents", [
            'document_type' => 'insurance',
            'title' => 'Test expiring_soon',
            'status' => 'expiring_soon',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    public function test_document_supports_all_valid_statuses(): void
    {
        $validStatuses = ['active', 'expired', 'pending_renewal', 'archived'];

        foreach ($validStatuses as $status) {
            $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/documents", [
                'document_type' => 'insurance',
                'title' => "Test {$status} status",
                'status' => $status,
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.status', $status);
        }
    }

    // ─── Fuel Types ──────────────────────────────────────────────

    public function test_fuel_record_supports_hybrid_and_other(): void
    {
        // Test hybrid
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/fuel-records", [
            'fill_date' => now()->toDateString(),
            'fuel_type' => 'hybrid',
            'quantity' => 40.0,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.fuel_type', 'hybrid');

        // Test other
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/fuel-records", [
            'fill_date' => now()->toDateString(),
            'fuel_type' => 'other',
            'quantity' => 30.0,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.fuel_type', 'other');
    }

    public function test_fuel_record_supports_all_fuel_types(): void
    {
        $fuelTypes = ['petrol', 'diesel', 'electric', 'hybrid', 'lpg', 'cng', 'hydrogen', 'other'];

        foreach ($fuelTypes as $type) {
            $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/fuel-records", [
                'fill_date' => now()->toDateString(),
                'fuel_type' => $type,
                'quantity' => 35.0,
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.fuel_type', $type);
        }

        $this->assertCount(count($fuelTypes), $this->vehicle->fuelRecords);
    }

    // ─── Checklist Status ────────────────────────────────────────

    public function test_checklist_supports_all_overall_statuses(): void
    {
        $statuses = [
            'passed',
            'passed_with_notes',
            'passed_with_issues',
            'failed',
            'requires_attention',
            'pending',
            'incomplete',
        ];

        foreach ($statuses as $status) {
            $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/checklists", [
                'checklist_type' => 'pre_trip',
                'title' => "Test {$status} checklist",
                'overall_status' => $status,
                'items_total' => 10,
                'items_passed' => 8,
                'items_failed' => 2,
            ]);

            $response->assertCreated();
            $response->assertJsonPath('data.overall_status', $status);
        }

        $this->assertCount(count($statuses), $this->vehicle->checklists);
    }

    // ─── Currency Defaults ───────────────────────────────────────

    public function test_maintenance_record_defaults_to_tnd_not_sar(): void
    {
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/maintenance-records", [
            'maintenance_type' => 'preventive',
            'title' => 'Test currency default',
            'scheduled_date' => now()->toDateString(),
            'total_cost' => 500.0,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.currency', 'TND');
    }

    public function test_fuel_record_defaults_to_tnd(): void
    {
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/fuel-records", [
            'fill_date' => now()->toDateString(),
            'fuel_type' => 'diesel',
            'quantity' => 60.0,
            'total_cost' => 120.0,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.currency', 'TND');
    }

    // ─── Data Migration Safety ───────────────────────────────────

    public function test_existing_records_preserved_after_enum_change(): void
    {
        // Create a maintenance record directly in the database
        $record = VehicleMaintenanceRecord::create([
            'vehicle_id' => $this->vehicle->id,
            'maintenance_number' => 'MAINT-TEST-001',
            'maintenance_type' => 'repair',
            'priority' => 'normal',
            'title' => 'Existing repair record',
            'scheduled_date' => now(),
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseHas('vehicle_maintenance_records', [
            'id' => $record->id,
            'maintenance_type' => 'repair',
            'priority' => 'normal',
        ]);

        // Verify it can be fetched via API
        $response = $this->getJson("/api/hr/fleet/maintenance-records/{$record->id}");
        $response->assertOk();
        $response->assertJsonPath('data.maintenance_type', 'repair');
        $response->assertJsonPath('data.priority', 'normal');
    }

    public function test_document_status_migration_mapping(): void
    {
        // Create a document with pending_renewal status
        $doc = VehicleDocument::create([
            'vehicle_id' => $this->vehicle->id,
            'document_type' => 'insurance',
            'title' => 'Test insurance document',
            'status' => 'pending_renewal',
        ]);

        $this->assertDatabaseHas('vehicle_documents', [
            'id' => $doc->id,
            'status' => 'pending_renewal',
        ]);

        // Verify it can be updated
        $response = $this->putJson("/api/hr/fleet/documents/{$doc->id}", [
            'status' => 'active',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');
    }

    // ─── Edge Cases ──────────────────────────────────────────────

    public function test_checklist_with_passed_with_notes_calculates_correctly(): void
    {
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/checklists", [
            'checklist_type' => 'pre_trip',
            'title' => 'Pre-trip with minor notes',
            'overall_status' => 'passed_with_notes',
            'items_total' => 15,
            'items_passed' => 14,
            'items_failed' => 0,
            'has_issues' => false,
            'notes' => 'Minor cosmetic observation - no safety impact',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.overall_status', 'passed_with_notes');
        $response->assertJsonPath('data.items_passed', 14);
        $response->assertJsonPath('data.has_issues', false);
    }

    public function test_maintenance_critical_priority_creates_correctly(): void
    {
        $response = $this->postJson("/api/hr/fleet/vehicles/{$this->vehicle->id}/maintenance-records", [
            'maintenance_type' => 'corrective',
            'priority' => 'critical',
            'title' => 'Critical brake system failure',
            'scheduled_date' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.priority', 'critical');
    }
}
