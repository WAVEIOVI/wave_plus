<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Models\Certification;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeCertification;
use App\Modules\Hr\Models\EmployeeSkill;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Models\Skill;
use App\Modules\Hr\Services\WorkforceAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkforceAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private WorkforceAvailabilityService $availabilityService;
    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->availabilityService = app(WorkforceAvailabilityService::class);

        $this->dept = Department::create([
            'name' => 'Field Operations',
            'code' => 'OPS',
        ]);
    }

    public function test_active_employee_is_available_for_date(): void
    {
        $employee = Employee::create([
            'first_name' => 'Paul',
            'last_name' => 'Direct',
            'email' => 'paul.direct@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);

        $available = $this->availabilityService->isEmployeeAvailable($employee->id, Carbon::parse('2026-10-01'));
        $this->assertTrue($available);
    }

    public function test_inactive_or_suspended_employee_is_not_available(): void
    {
        $suspended = Employee::create([
            'first_name' => 'Jacques',
            'last_name' => 'Block',
            'email' => 'jacques.block@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'suspended',
        ]);

        $available = $this->availabilityService->isEmployeeAvailable($suspended->id, Carbon::parse('2026-10-01'));
        $this->assertFalse($available);
    }

    public function test_employee_on_approved_leave_is_not_available(): void
    {
        $employee = Employee::create([
            'first_name' => 'Claire',
            'last_name' => 'Vacances',
            'email' => 'claire.v@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);

        $leaveType = LeaveType::create([
            'name' => 'Annual Leave',
            'code' => 'AL',
            'days_per_year' => 25,
            'is_paid' => true,
        ]);

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'total_days' => 5,
            'status' => 'approved',
        ]);

        // During leave -> false
        $availableDuring = $this->availabilityService->isEmployeeAvailable($employee->id, Carbon::parse('2026-10-03'));
        $this->assertFalse($availableDuring);

        // After leave -> true
        $availableAfter = $this->availabilityService->isEmployeeAvailable($employee->id, Carbon::parse('2026-10-10'));
        $this->assertTrue($availableAfter);
    }

    public function test_workforce_matching_respects_skills_and_levels(): void
    {
        $skill = Skill::create([
            'name' => 'High Voltage Electrician',
            'code' => 'ELEC-HV',
            'category' => 'technical',
        ]);

        // Employee 1: Expert level 4
        $emp1 = Employee::create([
            'first_name' => 'Expert',
            'last_name' => 'Electrician',
            'email' => 'expert.elec@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);
        EmployeeSkill::create([
            'employee_id' => $emp1->id,
            'skill_id' => $skill->id,
            'level' => 'expert',
        ]);

        // Employee 2: Beginner level 1
        $emp2 = Employee::create([
            'first_name' => 'Apprentice',
            'last_name' => 'Electrician',
            'email' => 'apprentice.elec@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);
        EmployeeSkill::create([
            'employee_id' => $emp2->id,
            'skill_id' => $skill->id,
            'level' => 'basic',
        ]);

        // Query requires level 3 (advanced or expert)
        $results = $this->availabilityService->findAvailableWorkforce([
            'date' => '2026-10-01',
            'required_skills' => [
                ['skill_id' => $skill->id, 'min_level' => 3],
            ],
        ]);

        $ids = collect($results)->pluck('employee_id')->all();
        $this->assertContains($emp1->id, $ids);
        $this->assertNotContains($emp2->id, $ids);
    }

    public function test_workforce_matching_excludes_expired_certifications(): void
    {
        $cert = Certification::create([
            'name' => 'Confined Space Safety',
            'code' => 'CSS-01',
            'validity_period_months' => 24,
        ]);

        // Employee with valid cert
        $validEmp = Employee::create([
            'first_name' => 'Valid',
            'last_name' => 'Holder',
            'email' => 'valid.holder@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);
        EmployeeCertification::create([
            'employee_id' => $validEmp->id,
            'certification_id' => $cert->id,
            'certificate_number' => 'CERT-VALID-001',
            'issue_date' => '2026-01-01',
            'expiry_date' => '2027-01-01',
            'status' => 'valid',
        ]);

        // Employee with expired cert
        $expiredEmp = Employee::create([
            'first_name' => 'Expired',
            'last_name' => 'Holder',
            'email' => 'expired.holder@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);
        EmployeeCertification::create([
            'employee_id' => $expiredEmp->id,
            'certification_id' => $cert->id,
            'certificate_number' => 'CERT-EXPIRED-001',
            'issue_date' => '2024-01-01',
            'expiry_date' => '2025-01-01',
            'status' => 'expired',
        ]);

        $results = $this->availabilityService->queryAvailability(
            Carbon::parse('2026-10-01 08:00:00'),
            Carbon::parse('2026-10-01 17:00:00'),
            [],
            [$cert->id]
        );

        $availableIds = $results->where('is_available', true)->pluck('employee_id')->all();

        $this->assertContains($validEmp->id, $availableIds);
        $this->assertNotContains($expiredEmp->id, $availableIds);
    }
}
