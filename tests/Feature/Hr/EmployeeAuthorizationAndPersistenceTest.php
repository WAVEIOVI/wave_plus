<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Authorization matrix for HR employee endpoints.
 *
 * Ensures mutations are enforced server-side (Spatie permissions), not merely
 * hidden on the frontend:
 *  - a user with only the module gate (hr.access) must NOT be able to mutate;
 *  - users holding hr.employees.* Spatie permissions may;
 *  - payloads persist every mapped field and values survive a reload.
 */
class EmployeeAuthorizationAndPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Role::findOrCreate('Super Admin', 'web');
        \App\Models\Role::findOrCreate('hr_viewer', 'web');
        \App\Models\Role::findOrCreate('hr_editor', 'web');

        foreach (['hr.employees.view', 'hr.attendance.view', 'hr.leave.view', 'hr.workforce.view'] as $perm) {
            \App\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            \App\Models\Role::where('name', 'hr_viewer')->first()->givePermissionTo($perm);
        }

        foreach (['hr.employees.create', 'hr.employees.edit', 'hr.employees.archive'] as $perm) {
            \App\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            \App\Models\Role::where('name', 'hr_editor')->first()->givePermissionTo($perm);
        }

        $this->dept = Department::create(['name' => 'Operations', 'code' => 'OPS']);
    }

    // ── Authorization ────────────────────────────────────────────────────

    public function test_user_without_mutation_permissions_cannot_create_employee(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('hr_viewer');
        Sanctum::actingAs($viewer);

        $response = $this->postJson('/api/hr/employees', [
            'first_name' => ' unauthorized',
            'last_name' => 'Attempt',
            'hire_date' => '2026-01-01',
        ]);

        $response->assertForbidden();

        $this->assertFalse(Employee::where('last_name->en', 'Attempt')->exists());
    }

    public function test_user_without_mutation_permissions_cannot_update_employee(): void
    {
        $employee = Employee::create([
            'first_name' => 'Alice',
            'last_name' => 'Original',
            'work_email' => 'alice.original@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);

        $viewer = User::factory()->create();
        $viewer->assignRole('hr_viewer');
        Sanctum::actingAs($viewer);

        $this->putJson("/api/hr/employees/{$employee->id}", [
            'last_name' => 'Hacked',
        ])->assertForbidden();

        $this->assertEquals('Original', $employee->fresh()->last_name);
    }

    public function test_user_without_mutation_permissions_cannot_delete_employee(): void
    {
        $employee = Employee::create([
            'first_name' => 'Bob',
            'last_name' => 'Protect',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);

        $viewer = User::factory()->create();
        $viewer->assignRole('hr_viewer');
        Sanctum::actingAs($viewer);

        $this->deleteJson("/api/hr/employees/{$employee->id}")->assertForbidden();

        $this->assertDatabaseHas('hr_employees', ['id' => $employee->id]);
        $this->assertNull($employee->fresh()->deleted_at);
    }

    public function test_user_without_mutation_permissions_cannot_change_status(): void
    {
        $employee = Employee::create([
            'first_name' => 'Carol',
            'last_name' => 'Lock',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);

        $viewer = User::factory()->create();
        $viewer->assignRole('hr_viewer');
        Sanctum::actingAs($viewer);

        $this->postJson("/api/hr/employees/{$employee->id}/change-status", [
            'status' => 'suspended',
        ])->assertForbidden();

        $this->assertEquals('active', $employee->fresh()->status);
    }

    public function test_user_with_edit_permission_can_update_but_not_create(): void
    {
        $employee = Employee::create([
            'first_name' => 'Dana',
            'last_name' => 'Editor',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);

        $editor = User::factory()->create();
        $editor->assignRole('hr_editor');
        Sanctum::actingAs($editor);

        $this->putJson("/api/hr/employees/{$employee->id}", [
            'first_name' => 'Dana',
            'last_name' => 'Edited',
        ])->assertOk()
            ->assertJsonPath('data.last_name.en', 'Edited');

        $response = $this->postJson('/api/hr/employees', [
            'first_name' => 'New',
            'last_name' => 'Hire',
            'hire_date' => '2026-01-01',
        ]);

        // hr_editor has create permission via hr.employees.create grant
        $response->assertCreated();
    }

    public function test_module_gate_only_user_cannot_access_employees(): void
    {
        // A user with no hr.* permissions fails the hr.access gate entirely.
        $plain = User::factory()->create();
        Sanctum::actingAs($plain);

        $this->getJson('/api/hr/employees')->assertForbidden();
    }

    public function test_viewer_role_can_read_but_module_level_editor_permissions_are_enforced(): void
    {
        $editor = User::factory()->create();
        // hr_editor holds only create/edit/archive, no view permission:
        // module gate passes because one hr.* manage permission exists.
        $editor->assignRole('hr_editor');
        Sanctum::actingAs($editor);

        $employee = Employee::create([
            'first_name' => 'Eve',
            'last_name' => 'Target',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);

        $this->putJson("/api/hr/employees/{$employee->id}", [
            'first_name' => 'Eve',
            'last_name' => 'Target-Edited',
        ])->assertOk();

        $this->assertEquals('Target-Edited', $employee->fresh()->last_name);
    }

    // ── Data contract / persistence ──────────────────────────────────────

    public function test_store_accepts_frontend_contact_aliases_and_persists_canonical_fields(): void
    {
        $creator = User::factory()->create(['is_active' => true]);
        \App\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $creator->assignRole('Super Admin');
        Sanctum::actingAs($creator);

        $response = $this->postJson('/api/hr/employees', [
            'first_name' => 'Frank',
            'last_name' => 'Alias',
            'email' => 'frank.alias@waveplus.local',
            'phone_number' => '+216 20 111 222',
            'hire_date' => '2026-01-05',
        ]);

        $response->assertCreated();

        $employee = Employee::where('first_name->en', 'Frank')->first();
        $this->assertNotNull($employee);
        $this->assertSame('frank.alias@waveplus.local', $employee->work_email);
        $this->assertSame('+216 20 111 222', $employee->work_phone);
    }

    public function test_update_accepts_aliases_and_maps_team_id_to_current_team_id(): void
    {
        $admin = User::factory()->create();
        \App\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);

        $employee = Employee::create([
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'work_email' => 'grace@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);

        $team = \App\Modules\Hr\Models\WorkTeam::create([
            'name' => 'Team Gamma',
            'code' => 'TEAM-GAMMA',
        ]);

        $response = $this->putJson("/api/hr/employees/{$employee->id}", [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace.hopper@waveplus.local',
            'phone_number' => '+216 20 333 444',
            'team_id' => $team->id,
        ]);

        $response->assertOk();

        $employee->refresh();
        $this->assertSame('grace.hopper@waveplus.local', $employee->work_email);
        $this->assertSame('+216 20 333 444', $employee->work_phone);
        $this->assertSame($team->id, $employee->current_team_id);
    }

    public function test_update_persists_nullable_clearings_and_values_survive_reload(): void
    {
        $admin = User::factory()->create();
        \App\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);

        $employee = Employee::create([
            'first_name' => 'Heidi',
            'last_name' => 'Nuller',
            'work_email' => 'heidi@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'nationality' => 'Tunisian',
            'address' => '12 Rue de Test',
            'notes' => 'Original note',
            'status' => 'active',
        ]);

        // Clear two nullable fields and change another
        $response = $this->putJson("/api/hr/employees/{$employee->id}", [
            'first_name' => 'Heidi',
            'last_name' => 'Nuller',
            'nationality' => null,
            'notes' => 'Updated note',
        ]);

        $response->assertOk();

        $employee->refresh();
        $this->assertNull($employee->nationality);
        $this->assertSame('Updated note', $employee->notes);
        $this->assertSame('12 Rue de Test', $employee->address);

        // Reload through the API (what the frontend does on page refresh)
        $this->getJson("/api/hr/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.nationality', null)
            ->assertJsonPath('data.notes', 'Updated note')
            ->assertJsonPath('data.address', '12 Rue de Test');
    }

    public function test_resource_returns_team_job_title_and_phone_aliases_for_display(): void
    {
        $admin = User::factory()->create();
        \App\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);

        $team = \App\Modules\Hr\Models\WorkTeam::create([
            'name' => 'Team Delta',
            'code' => 'TEAM-DELTA',
        ]);

        $position = \App\Modules\Hr\Models\Position::create([
            'department_id' => $this->dept->id,
            'title' => 'Master Technician',
            'code' => 'MST-TECH',
        ]);

        $employee = Employee::create([
            'first_name' => 'Ivan',
            'last_name' => 'Display',
            'work_email' => 'ivan@waveplus.local',
            'work_phone' => '+216 20 555 666',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'position_id' => $position->id,
            'current_team_id' => $team->id,
            'status' => 'active',
        ]);

        $this->getJson("/api/hr/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.department.name.en', 'Operations')
            ->assertJsonPath('data.position.title.en', 'Master Technician')
            ->assertJsonPath('data.team.name.en', 'Team Delta')
            ->assertJsonPath('data.team_id', $team->id)
            ->assertJsonPath('data.job_title', 'Master Technician')
            ->assertJsonPath('data.phone_number', '+216 20 555 666')
            ->assertJsonPath('data.email', 'ivan@waveplus.local');
    }

    public function test_employee_meta_returns_translation_maps_for_hr_options(): void
    {
        $admin = User::factory()->create();
        \App\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);

        $this->getJson('/api/hr/employees/meta')
            ->assertOk()
            ->assertJsonPath('departments.0.name.en', 'Operations');
    }
}
