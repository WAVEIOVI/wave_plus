<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Models\WorkTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentPositionListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        Sanctum::actingAs(User::factory()->create()->assignRole('Super Admin'));
    }

    public function test_department_filters_and_pagination_are_applied(): void
    {
        $parent = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        Department::create([
            'name' => 'Active Team',
            'code' => 'OPS-ACT',
            'parent_department_id' => $parent->id,
            'status' => 'active',
        ]);
        $inactiveDepartment = Department::create([
            'name' => 'Inactive Team',
            'code' => 'OPS-INACT',
            'parent_department_id' => $parent->id,
            'status' => 'inactive',
        ]);

        $this->getJson("/api/hr/departments?status=inactive&parent_department_id={$parent->id}&page=1&per_page=1")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $inactiveDepartment->id)
            ->assertJsonPath('stats.total_count', 3)
            ->assertJsonPath('stats.active_count', 2)
            ->assertJsonPath('stats.with_manager_count', 0);
    }

    public function test_position_filters_and_pagination_are_applied(): void
    {
        $department = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        Position::create([
            'title' => 'Analyst',
            'code' => 'FIN-AN',
            'department_id' => $department->id,
            'status' => 'active',
        ]);
        $inactivePosition = Position::create([
            'title' => 'Senior Analyst',
            'code' => 'FIN-SAN',
            'department_id' => $department->id,
            'status' => 'inactive',
        ]);

        $this->getJson("/api/hr/positions?status=inactive&department_id={$department->id}&search=Senior&page=1&per_page=1")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $inactivePosition->id)
            ->assertJsonPath('stats.total_count', 2)
            ->assertJsonPath('stats.active_count', 1)
            ->assertJsonPath('stats.with_rate_count', 0)
            ->assertJsonPath('stats.employees_count', 0);
    }

    public function test_department_detail_includes_employees_and_work_teams(): void
    {
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $employee = Employee::create([
            'first_name' => 'Taylor',
            'last_name' => 'Smith',
            'hire_date' => '2026-01-01',
            'department_id' => $department->id,
        ]);
        $team = WorkTeam::create([
            'name' => 'Field Team',
            'code' => 'FIELD',
            'department_id' => $department->id,
            'team_leader_id' => $employee->id,
        ]);

        $this->getJson("/api/hr/departments/{$department->id}")
            ->assertOk()
            ->assertJsonPath('data.employees.0.id', $employee->id)
            ->assertJsonPath('data.employees.0.full_name', 'Taylor Smith')
            ->assertJsonPath('data.work_teams.0.id', $team->id)
            ->assertJsonPath('data.work_teams.0.team_leader_name', 'Taylor Smith')
            ->assertJsonPath('data.employees_count', 1)
            ->assertJsonPath('data.teams_count', 1);
    }

    public function test_position_detail_includes_assigned_employees(): void
    {
        $position = Position::create([
            'title' => 'Technician',
            'code' => 'TECH',
        ]);
        $employee = Employee::create([
            'first_name' => 'Jordan',
            'last_name' => 'Lee',
            'hire_date' => '2026-01-01',
            'position_id' => $position->id,
        ]);

        $this->getJson("/api/hr/positions/{$position->id}")
            ->assertOk()
            ->assertJsonPath('data.employees.0.id', $employee->id)
            ->assertJsonPath('data.employees.0.full_name', 'Jordan Lee');
    }
}
