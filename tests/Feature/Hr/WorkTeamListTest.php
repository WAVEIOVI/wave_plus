<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\WorkTeam;
use App\Modules\Hr\Models\WorkTeamMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkTeamListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        Sanctum::actingAs(User::factory()->create()->assignRole('Super Admin'));
    }

    public function test_team_list_filters_paginates_and_returns_global_summary_and_types(): void
    {
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $leader = Employee::create([
            'first_name' => 'Taylor',
            'last_name' => 'Smith',
            'hire_date' => '2026-01-01',
            'department_id' => $department->id,
        ]);

        $matchingTeam = WorkTeam::create([
            'name' => 'Alpha',
            'code' => 'ALPHA',
            'team_type' => 'Intervention',
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        WorkTeam::create([
            'name' => 'Bravo',
            'code' => 'BRAVO',
            'team_type' => 'Maintenance',
            'team_leader_id' => $leader->id,
            'is_active' => false,
        ]);
        WorkTeam::create([
            'name' => 'Charlie',
            'code' => 'CHARLIE',
            'team_type' => 'Maintenance',
            'team_leader_id' => $leader->id,
            'is_active' => true,
        ]);
        WorkTeamMember::create([
            'team_id' => $matchingTeam->id,
            'employee_id' => $leader->id,
            'joined_at' => '2026-01-01',
            'role_in_team' => 'Member',
        ]);

        $this->getJson('/api/hr/teams?status=active&team_type=Intervention&department_id='.$department->id.'&has_leader=0&page=1&per_page=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('data.0.id', $matchingTeam->id)
            ->assertJsonPath('data.0.members_count', 1)
            ->assertJsonPath('stats.total_count', 3)
            ->assertJsonPath('stats.active_count', 2)
            ->assertJsonPath('stats.with_leader_count', 2)
            ->assertJsonPath('stats.total_members_count', 1)
            ->assertJsonFragment(['team_types' => ['Intervention', 'Maintenance']]);
    }

    public function test_team_detail_serializes_active_member_names_and_employee_id(): void
    {
        $employee = Employee::create([
            'first_name' => 'Jordan',
            'last_name' => 'Lee',
            'hire_date' => '2026-01-01',
        ]);
        $team = WorkTeam::create([
            'name' => 'Field Team',
            'code' => 'FIELD',
            'team_leader_id' => $employee->id,
        ]);
        WorkTeamMember::create([
            'team_id' => $team->id,
            'employee_id' => $employee->id,
            'joined_at' => '2026-01-01',
            'role_in_team' => 'Supervisor',
        ]);

        $this->getJson("/api/hr/teams/{$team->id}")
            ->assertOk()
            ->assertJsonPath('data.members.0.employee_id', $employee->id)
            ->assertJsonPath('data.members.0.full_name', 'Jordan Lee')
            ->assertJsonPath('data.members.0.first_name.en', 'Jordan')
            ->assertJsonPath('data.members.0.last_name.en', 'Lee')
            ->assertJsonPath('data.members.0.is_primary', true)
            ->assertJsonPath('data.members.0.joined_at', '2026-01-01')
            ->assertJsonPath('data.team_leader_id', $employee->id)
            ->assertJsonPath('data.members.0.role_in_team', 'Supervisor')
            ->assertJsonStructure(['data' => ['updated_at']]);
    }
}
