<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\JobOpening;
use App\Modules\Hr\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CandidateToEmployeeHireTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Department $dept;
    private Position $position;
    private JobOpening $opening;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        \App\Models\Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->dept = Department::create([
            'name' => 'Workshop Technical',
            'code' => 'WKP',
        ]);

        $this->position = Position::create([
            'department_id' => $this->dept->id,
            'title' => 'Hydraulics Specialist',
            'code' => 'HYDR-01',
            'standard_hourly_rate' => 30.00,
        ]);

        $this->opening = JobOpening::create([
            'title' => 'Hydraulics Specialist Opening',
            'department_id' => $this->dept->id,
            'position_id' => $this->position->id,
            'openings_count' => 2,
            'status' => 'published',
        ]);
    }

    public function test_can_register_candidate_with_auto_generated_reference(): void
    {
        $response = $this->postJson('/api/hr/recruitment/candidates', [
            'first_name' => 'Karim',
            'last_name' => 'Zidane',
            'email' => 'karim.zidane@example.com',
            'phone' => '+33 6 98 76 54 32',
            'job_opening_id' => $this->opening->id,
            'notes' => '5 years of hydraulic systems repair experience',
        ]);

        $response->assertCreated();
        $ref = $response->json('data.reference');
        $this->assertNotNull($ref);
        $this->assertStringStartsWith('CAN-', $ref);

        $this->assertDatabaseHas('hr_candidates', [
            'email' => 'karim.zidane@example.com',
            'status' => 'applied',
        ]);
    }

    public function test_candidate_stage_transitions(): void
    {
        $candidate = Candidate::create([
            'first_name' => 'Nadia',
            'last_name' => 'Belkacem',
            'email' => 'nadia.b@example.com',
            'status' => 'applied',
        ]);

        $response = $this->patchJson("/api/hr/recruitment/candidates/{$candidate->id}/stage", [
            'status' => 'interview',
        ]);

        $response->assertOk();
        $this->assertEquals('interview', $candidate->fresh()->status);
    }

    public function test_atomic_candidate_to_employee_hire_conversion(): void
    {
        $candidate = Candidate::create([
            'first_name' => 'David',
            'last_name' => 'Rousseau',
            'email' => 'david.rousseau@example.com',
            'phone' => '+33 6 11 22 33 44',
            'status' => 'selected',
        ]);

        $hirePayload = [
            'department_id' => $this->dept->id,
            'position_id' => $this->position->id,
            'hire_date' => '2026-10-01',
            'work_email' => 'david.rousseau@waveplus.local',
            'work_phone' => '+33 1 23 45 67 89',
            'employment_type' => 'full_time',
            'contract_type' => 'permanent',
        ];

        $response = $this->postJson("/api/hr/recruitment/candidates/{$candidate->id}/hire", $hirePayload);

        $response->assertCreated();

        // 1. Candidate status changed to hired
        $this->assertEquals('hired', $candidate->fresh()->status);

        // 2. New employee record created
        $employee = Employee::where('work_email', 'david.rousseau@waveplus.local')->first();
        $this->assertNotNull($employee);
        $this->assertStringStartsWith('EMP-', $employee->employee_number);
        $this->assertEquals('active', $employee->status);
        $this->assertEquals($this->dept->id, $employee->department_id);
        $this->assertEquals($this->position->id, $employee->position_id);

        // 3. Initial employment history recorded
        $this->assertDatabaseHas('hr_employment_histories', [
            'employee_id' => $employee->id,
            'department_id' => $this->dept->id,
            'position_id' => $this->position->id,
            'reason_for_change' => 'Initial hire',
        ]);

        // 4. Audit log entry recorded
        $this->assertDatabaseHas('hr_audit_logs', [
            'auditable_type' => Employee::class,
            'auditable_id' => $employee->id,
            'action' => 'HIRE_CANDIDATE',
        ]);
    }
}
