<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\AppraisalCycle;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeAppraisal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceAppraisalsTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrManager;

    protected User $evaluator;

    protected User $employeeUser;

    protected Employee $employee;

    protected User $otherEmployeeUser;

    protected Employee $otherEmployee;

    protected User $generalViewer;

    protected AppraisalCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();

        $managerRole = Role::findOrCreate('HR Manager', 'web');
        $evaluatorRole = Role::findOrCreate('HR Evaluator', 'web');
        $viewerRole = Role::findOrCreate('General Viewer', 'web');

        $permissions = [
            'hr.appraisals.view',
            'hr.appraisals.manage',
            'hr.employees.view',
            'read-PeopleSkills',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $managerRole->givePermissionTo(['hr.appraisals.view', 'hr.appraisals.manage', 'hr.employees.view']);
        $evaluatorRole->givePermissionTo(['hr.appraisals.view']);
        $viewerRole->givePermissionTo(['hr.employees.view', 'read-PeopleSkills']);

        $this->hrManager = User::factory()->create(['name' => 'HR Manager User']);
        $this->hrManager->assignRole($managerRole);

        $this->evaluator = User::factory()->create(['name' => 'Evaluator User']);
        $this->evaluator->assignRole($evaluatorRole);

        $this->generalViewer = User::factory()->create(['name' => 'General Viewer User']);
        $this->generalViewer->assignRole($viewerRole);

        $department = Department::create([
            'name' => 'Engineering',
            'code' => 'ENG',
            'is_active' => true,
        ]);

        $this->employeeUser = User::factory()->create(['name' => 'John Doe']);
        $this->employee = Employee::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'matricule' => 'EMP-001',
            'email' => 'john.doe@example.com',
            'user_id' => $this->employeeUser->id,
            'department_id' => $department->id,
            'status' => 'active',
            'hire_date' => '2025-01-01',
        ]);

        $this->otherEmployeeUser = User::factory()->create(['name' => 'Jane Smith']);
        $this->otherEmployee = Employee::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'matricule' => 'EMP-002',
            'email' => 'jane.smith@example.com',
            'user_id' => $this->otherEmployeeUser->id,
            'department_id' => $department->id,
            'status' => 'active',
            'hire_date' => '2025-01-01',
        ]);

        $this->cycle = AppraisalCycle::create([
            'name' => 'Annual Performance Review 2026',
            'cycle_type' => 'annual',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);
    }

    public function test_can_create_and_manage_appraisal_cycles(): void
    {
        Sanctum::actingAs($this->hrManager);

        $response = $this->postJson('/api/hr/appraisals/cycles', [
            'name' => 'Q1 2026 Probation Reviews',
            'cycle_type' => 'probation',
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'status' => 'draft',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Q1 2026 Probation Reviews')
            ->assertJsonPath('data.cycle_type', 'probation');

        $cycleId = $response->json('data.id');

        $updateResponse = $this->putJson("/api/hr/appraisals/cycles/{$cycleId}", [
            'name' => 'Q1 2026 Probation Reviews - Revised',
            'cycle_type' => 'probation',
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-15',
            'status' => 'active',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.name', 'Q1 2026 Probation Reviews - Revised')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_prevents_deleting_cycle_with_existing_appraisals(): void
    {
        Sanctum::actingAs($this->hrManager);

        EmployeeAppraisal::create([
            'appraisal_cycle_id' => $this->cycle->id,
            'employee_id' => $this->employee->id,
            'reviewer_id' => $this->hrManager->id,
            'overall_score' => 4.20,
            'status' => 'submitted',
        ]);

        $response = $this->deleteJson("/api/hr/appraisals/cycles/{$this->cycle->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('hr_appraisal_cycles', ['id' => $this->cycle->id]);
    }

    public function test_allows_deleting_cycle_without_appraisals(): void
    {
        Sanctum::actingAs($this->hrManager);

        $emptyCycle = AppraisalCycle::create([
            'name' => 'Empty Cycle',
            'cycle_type' => 'project',
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-01',
            'status' => 'draft',
        ]);

        $response = $this->deleteJson("/api/hr/appraisals/cycles/{$emptyCycle->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('hr_appraisal_cycles', ['id' => $emptyCycle->id]);
    }

    public function test_creates_appraisal_and_computes_score_from_ratings(): void
    {
        Sanctum::actingAs($this->hrManager);

        $ratings = [
            'job_knowledge' => 4,
            'work_quality' => 5,
            'productivity' => 4,
            'communication' => 3,
            'initiative' => 4,
        ];

        $response = $this->postJson('/api/hr/appraisals', [
            'appraisal_cycle_id' => $this->cycle->id,
            'employee_id' => $this->employee->id,
            'reviewer_id' => $this->hrManager->id,
            'ratings' => $ratings,
            'status' => 'reviewed',
            'manager_feedback' => 'Consistently exceeds performance expectations.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'reviewed')
            ->assertJsonPath('data.overall_score', 4);

        $appraisal = EmployeeAppraisal::where('employee_id', $this->employee->id)->first();
        $this->assertNotNull($appraisal);
        $this->assertEquals(4.00, (float) $appraisal->overall_score);
        $this->assertNotNull($appraisal->reviewed_at);
    }

    public function test_prevents_modifying_acknowledged_appraisal(): void
    {
        Sanctum::actingAs($this->hrManager);

        $appraisal = EmployeeAppraisal::create([
            'appraisal_cycle_id' => $this->cycle->id,
            'employee_id' => $this->employee->id,
            'reviewer_id' => $this->hrManager->id,
            'overall_score' => 4.50,
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $this->employeeUser->id,
            'manager_feedback' => 'Initial feedback',
        ]);

        $updateResponse = $this->putJson("/api/hr/appraisals/{$appraisal->id}", [
            'overall_score' => 2.00,
            'manager_feedback' => 'Attempted tamper after acknowledgment',
        ]);

        $updateResponse->assertStatus(422);

        $fresh = $appraisal->fresh();
        $this->assertEquals('Initial feedback', $fresh->manager_feedback);
        $this->assertEquals(4.50, (float) $fresh->overall_score);

        // Also test storeAppraisal updateOrCreate guard
        $reStoreResponse = $this->postJson('/api/hr/appraisals', [
            'appraisal_cycle_id' => $this->cycle->id,
            'employee_id' => $this->employee->id,
            'overall_score' => 1.00,
        ]);

        $reStoreResponse->assertStatus(422);
    }

    public function test_prevents_deleting_acknowledged_appraisal(): void
    {
        Sanctum::actingAs($this->hrManager);

        $appraisal = EmployeeAppraisal::create([
            'appraisal_cycle_id' => $this->cycle->id,
            'employee_id' => $this->employee->id,
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $this->employeeUser->id,
        ]);

        $response = $this->deleteJson("/api/hr/appraisals/{$appraisal->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('hr_employee_appraisals', ['id' => $appraisal->id]);
    }

    public function test_allows_employee_to_acknowledge_their_appraisal(): void
    {
        $appraisal = EmployeeAppraisal::create([
            'appraisal_cycle_id' => $this->cycle->id,
            'employee_id' => $this->employee->id,
            'reviewer_id' => $this->hrManager->id,
            'overall_score' => 4.25,
            'status' => 'reviewed',
            'reviewed_at' => now(),
            'manager_feedback' => 'Great job this year.',
        ]);

        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson("/api/hr/appraisals/{$appraisal->id}/acknowledge", [
            'employee_feedback' => 'Thank you. I agree with the review and objectives.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'acknowledged')
            ->assertJsonPath('data.employee_feedback', 'Thank you. I agree with the review and objectives.');

        $fresh = $appraisal->fresh();
        $this->assertEquals('acknowledged', $fresh->status);
        $this->assertNotNull($fresh->acknowledged_at);
        $this->assertEquals($this->employeeUser->id, $fresh->acknowledged_by);
        $this->assertTrue($fresh->is_completed);
    }

    public function test_prevents_another_employee_from_acknowledging(): void
    {
        $appraisal = EmployeeAppraisal::create([
            'appraisal_cycle_id' => $this->cycle->id,
            'employee_id' => $this->employee->id,
            'status' => 'reviewed',
        ]);

        Sanctum::actingAs($this->otherEmployeeUser);

        $response = $this->postJson("/api/hr/appraisals/{$appraisal->id}/acknowledge", [
            'employee_feedback' => 'Malicious attempt',
        ]);

        $response->assertStatus(403);
    }

    public function test_prevents_acknowledging_draft_appraisal(): void
    {
        $appraisal = EmployeeAppraisal::create([
            'appraisal_cycle_id' => $this->cycle->id,
            'employee_id' => $this->employee->id,
            'status' => 'draft',
        ]);

        Sanctum::actingAs($this->employeeUser);

        $response = $this->postJson("/api/hr/appraisals/{$appraisal->id}/acknowledge");

        $response->assertStatus(422);
    }

    public function test_protects_confidential_evaluations_from_unauthorized_users(): void
    {
        $appraisal = EmployeeAppraisal::create([
            'appraisal_cycle_id' => $this->cycle->id,
            'employee_id' => $this->employee->id,
            'reviewer_id' => $this->hrManager->id,
            'overall_score' => 4.00,
            'status' => 'submitted',
        ]);

        // General viewer with only hr.employees.view and read-PeopleSkills cannot view company-wide appraisals
        Sanctum::actingAs($this->generalViewer);

        $listResponse = $this->getJson('/api/hr/appraisals');
        // Because generalViewer is not an employee and has no hr.appraisals.view permission, abort 403
        $listResponse->assertStatus(403);

        $detailResponse = $this->getJson("/api/hr/appraisals/{$appraisal->id}");
        $detailResponse->assertStatus(403);

        // Employee A CAN view their own appraisal
        Sanctum::actingAs($this->employeeUser);
        $selfResponse = $this->getJson("/api/hr/appraisals/{$appraisal->id}");
        $selfResponse->assertStatus(200)
            ->assertJsonPath('data.id', $appraisal->id);

        // Employee B CANNOT view Employee A's appraisal
        Sanctum::actingAs($this->otherEmployeeUser);
        $otherResponse = $this->getJson("/api/hr/appraisals/{$appraisal->id}");
        $otherResponse->assertStatus(403);
    }

    public function test_returns_appraisal_kpi_summary(): void
    {
        Sanctum::actingAs($this->hrManager);

        EmployeeAppraisal::create([
            'appraisal_cycle_id' => $this->cycle->id,
            'employee_id' => $this->employee->id,
            'overall_score' => 4.00,
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $this->employeeUser->id,
        ]);

        EmployeeAppraisal::create([
            'appraisal_cycle_id' => $this->cycle->id,
            'employee_id' => $this->otherEmployee->id,
            'overall_score' => 3.50,
            'status' => 'reviewed',
            'reviewed_at' => now(),
        ]);

        $response = $this->getJson('/api/hr/appraisals/kpis');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_cycles', 1)
            ->assertJsonPath('data.active_cycles', 1)
            ->assertJsonPath('data.total_appraisals', 2)
            ->assertJsonPath('data.completed_appraisals', 1)
            ->assertJsonPath('data.in_review_appraisals', 1)
            ->assertJsonPath('data.completion_rate', 50)
            ->assertJsonPath('data.average_score', 3.75);
    }
}
