<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\AppraisalCycle;
use App\Modules\Hr\Models\Attendance;
use App\Modules\Hr\Models\AttendanceCorrection;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeAppraisal;
use App\Modules\Hr\Models\JobOpening;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Models\Vehicle;
use Database\Seeders\HrSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrAuthorizationAndRolesTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;
    private Position $position;
    private LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(HrSeeder::class);

        $this->department = Department::firstOrCreate(
            ['code' => 'TEST-DEP'],
            ['name' => ['en' => 'Test Department', 'fr' => 'Département Test']]
        );

        $this->position = Position::firstOrCreate(
            ['code' => 'TEST-POS'],
            [
                'title' => ['en' => 'Test Engineer', 'fr' => 'Ingénieur Test'],
                'department_id' => $this->department->id,
                'standard_hourly_rate' => 75.50,
            ]
        );

        $this->leaveType = LeaveType::firstOrCreate(
            ['code' => 'ANNUAL'],
            [
                'name' => ['en' => 'Annual Leave', 'fr' => 'Congés Payés'],
                'max_days_per_year' => 25,
            ]
        );
    }

    public function test_hr_role_templates_exist_and_have_exact_permissions(): void
    {
        $expectedRoles = [
            'HR Manager',
            'HR Officer',
            'Attendance Manager',
            'Leave Approver',
            'Recruiter',
            'Fleet Manager',
            'Employee Self-Service',
        ];

        foreach ($expectedRoles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $this->assertNotNull($role, "Role template [{$roleName}] must exist.");
        }

        $hrManager = Role::findByName('HR Manager', 'web');
        $this->assertTrue($hrManager->hasPermissionTo('hr.employees.create'));
        $this->assertTrue($hrManager->hasPermissionTo('hr.employees.archive'));
        $this->assertTrue($hrManager->hasPermissionTo('hr.employees.view-sensitive'));
        $this->assertTrue($hrManager->hasPermissionTo('hr.leave.approve'));
        $this->assertTrue($hrManager->hasPermissionTo('hr.attendance.correct'));

        $hrOfficer = Role::findByName('HR Officer', 'web');
        $this->assertTrue($hrOfficer->hasPermissionTo('hr.employees.create'));
        $this->assertTrue($hrOfficer->hasPermissionTo('hr.employees.edit'));
        $this->assertFalse($hrOfficer->hasPermissionTo('hr.employees.archive'));
        $this->assertFalse($hrOfficer->hasPermissionTo('hr.employees.view-sensitive'));
        $this->assertFalse($hrOfficer->hasPermissionTo('hr.leave.approve'));

        $attendanceMgr = Role::findByName('Attendance Manager', 'web');
        $this->assertTrue($attendanceMgr->hasPermissionTo('hr.attendance.view'));
        $this->assertTrue($attendanceMgr->hasPermissionTo('hr.attendance.correct'));
        $this->assertFalse($attendanceMgr->hasPermissionTo('hr.employees.create'));
        $this->assertFalse($attendanceMgr->hasPermissionTo('hr.leave.approve'));

        $leaveApprover = Role::findByName('Leave Approver', 'web');
        $this->assertTrue($leaveApprover->hasPermissionTo('hr.leave.approve'));
        $this->assertFalse($leaveApprover->hasPermissionTo('hr.employees.create'));
        $this->assertFalse($leaveApprover->hasPermissionTo('hr.attendance.correct'));

        $recruiter = Role::findByName('Recruiter', 'web');
        $this->assertTrue($recruiter->hasPermissionTo('hr.recruitment.view'));
        $this->assertTrue($recruiter->hasPermissionTo('hr.recruitment.hire'));
        $this->assertFalse($recruiter->hasPermissionTo('hr.leave.approve'));

        $fleetMgr = Role::findByName('Fleet Manager', 'web');
        $this->assertTrue($fleetMgr->hasPermissionTo('hr.fleet.view'));
        $this->assertTrue($fleetMgr->hasPermissionTo('hr.fleet.manage'));
        $this->assertFalse($fleetMgr->hasPermissionTo('hr.leave.approve'));
    }

    public function test_hr_manager_has_full_management_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole('HR Manager');
        Sanctum::actingAs($user);

        // Can view dashboard
        $this->getJson('/api/hr/dashboard-stats')->assertOk();

        // Can create employee
        $response = $this->postJson('/api/hr/employees', [
            'first_name' => ['en' => 'Jane'],
            'last_name' => ['en' => 'Doe'],
            'work_email' => 'jane.doe@waveplus.test',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'hire_date' => '2026-01-15',
            'status' => 'active',
        ]);
        $response->assertCreated();
        $employeeId = $response->json('data.id');

        // Can view employee with sensitive fields
        $showRes = $this->getJson("/api/hr/employees/{$employeeId}");
        $showRes->assertOk();

        // Can view positions with standard hourly rate
        $posRes = $this->getJson('/api/hr/positions');
        $posRes->assertOk();
        $posItem = collect($posRes->json('data'))->firstWhere('code', 'TEST-POS');
        $this->assertNotNull($posItem);
        $this->assertEquals(75.50, $posItem['standard_hourly_rate']);
    }

    public function test_hr_officer_cannot_archive_or_view_salary(): void
    {
        $user = User::factory()->create();
        $user->assignRole('HR Officer');
        Sanctum::actingAs($user);

        // Can create employee
        $createRes = $this->postJson('/api/hr/employees', [
            'first_name' => ['en' => 'Mark'],
            'last_name' => ['en' => 'Smith'],
            'work_email' => 'mark.smith@waveplus.test',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'hire_date' => '2026-02-01',
        ]);
        $createRes->assertCreated();
        $empId = $createRes->json('data.id');

        // Cannot delete employee (403)
        $this->deleteJson("/api/hr/employees/{$empId}")->assertForbidden();

        // Positions query masks salary rate
        $posRes = $this->getJson('/api/hr/positions');
        $posRes->assertOk();
        $posItem = collect($posRes->json('data'))->firstWhere('code', 'TEST-POS');
        $this->assertNotNull($posItem);
        $this->assertNull($posItem['standard_hourly_rate']);
    }

    public function test_attendance_manager_access_and_restrictions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Attendance Manager');
        Sanctum::actingAs($user);

        // Can view attendance
        $this->getJson('/api/hr/attendances')->assertOk();

        // Cannot create employee (403)
        $this->postJson('/api/hr/employees', [
            'first_name' => ['en' => 'No'],
            'last_name' => ['en' => 'Access'],
        ])->assertForbidden();

        // Cannot view recruitment candidates (403)
        $this->getJson('/api/hr/recruitment/candidates')->assertForbidden();

        // Cannot mutate performance appraisals (403)
        $this->postJson('/api/hr/appraisals/cycles', [
            'name' => 'Unauthorized Cycle',
        ])->assertForbidden();

        // Cannot view fleet vehicles (403)
        $this->getJson('/api/hr/fleet/vehicles')->assertForbidden();
    }

    public function test_leave_approver_cannot_approve_own_leave(): void
    {
        $approverUser = User::factory()->create();
        $approverUser->assignRole('Leave Approver');

        $colleagueUser = User::factory()->create();
        $colleagueEmp = Employee::create([
            'user_id' => $colleagueUser->id,
            'employee_number' => 'EMP-COL-001',
            'first_name' => ['en' => 'Colleague'],
            'last_name' => ['en' => 'Person'],
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'status' => 'active',
            'hire_date' => '2026-01-01',
        ]);

        $approverEmp = Employee::create([
            'user_id' => $approverUser->id,
            'employee_number' => 'EMP-APP-001',
            'first_name' => ['en' => 'Approver'],
            'last_name' => ['en' => 'Manager'],
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'status' => 'active',
            'hire_date' => '2026-01-01',
        ]);

        // Colleague leave request
        $colleagueReq = LeaveRequest::create([
            'employee_id' => $colleagueEmp->id,
            'requested_by_id' => $colleagueUser->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
            'total_days' => 5,
            'status' => 'submitted',
        ]);

        // Approver's own leave request
        $ownReq = LeaveRequest::create([
            'employee_id' => $approverEmp->id,
            'requested_by_id' => $approverUser->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
            'total_days' => 5,
            'status' => 'submitted',
        ]);

        Sanctum::actingAs($approverUser);

        // Can approve colleague's leave
        $this->postJson("/api/hr/leave-requests/{$colleagueReq->id}/approve")->assertOk();

        // CANNOT approve their own leave request (403)
        $this->postJson("/api/hr/leave-requests/{$ownReq->id}/approve")->assertForbidden();
    }

    public function test_employee_self_service_restrictions(): void
    {
        $employeeUser = User::factory()->create();
        $employeeUser->assignRole('Employee Self-Service');

        $ownEmployee = Employee::create([
            'user_id' => $employeeUser->id,
            'employee_number' => 'EMP-SELF-001',
            'first_name' => ['en' => 'Self'],
            'last_name' => ['en' => 'Service'],
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'status' => 'active',
            'hire_date' => '2026-01-01',
        ]);

        $otherUser = User::factory()->create();
        $otherEmployee = Employee::create([
            'user_id' => $otherUser->id,
            'employee_number' => 'EMP-OTHER-001',
            'first_name' => ['en' => 'Other'],
            'last_name' => ['en' => 'Employee'],
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'status' => 'active',
            'hire_date' => '2026-01-01',
        ]);

        Sanctum::actingAs($employeeUser);

        // Can view own employee profile
        $this->getJson("/api/hr/employees/{$ownEmployee->id}")->assertOk();

        // CANNOT view other employee's profile (403)
        $this->getJson("/api/hr/employees/{$otherEmployee->id}")->assertForbidden();

        // CANNOT view the employee directory list (403)
        $this->getJson('/api/hr/employees')->assertForbidden();

        // CANNOT access recruitment candidates (403)
        $this->getJson('/api/hr/recruitment/candidates')->assertForbidden();

        // CANNOT adjust leave balances (403)
        $this->postJson('/api/hr/leave-balances/adjust', [
            'employee_id' => $ownEmployee->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => 2026,
            'accrued_days' => 50,
        ])->assertForbidden();
    }

    public function test_general_read_people_skills_does_not_grant_mutations_or_approvals(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('read-PeopleSkills');
        Sanctum::actingAs($user);

        // Can view dashboard
        $this->getJson('/api/hr/dashboard-stats')->assertOk();

        // Can view employees
        $this->getJson('/api/hr/employees')->assertOk();

        // CANNOT create employee
        $this->postJson('/api/hr/employees', [
            'first_name' => ['en' => 'Forbidden'],
            'last_name' => ['en' => 'Create'],
        ])->assertForbidden();

        // CANNOT create department
        $this->postJson('/api/hr/departments', [
            'code' => 'NEW-DEP',
            'name' => ['en' => 'New Dep'],
        ])->assertForbidden();

        // CANNOT approve leave requests
        $req = LeaveRequest::create([
            'employee_id' => Employee::create([
                'employee_number' => 'EMP-TEST-099',
                'first_name' => ['en' => 'Emp'],
                'last_name' => ['en' => 'Test'],
                'department_id' => $this->department->id,
                'position_id' => $this->position->id,
                'status' => 'active',
                'hire_date' => '2026-01-01',
            ])->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-05',
            'total_days' => 5,
            'status' => 'submitted',
        ]);
        $this->postJson("/api/hr/leave-requests/{$req->id}/approve")->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/hr/employees')->assertUnauthorized();
        $this->getJson('/api/hr/dashboard-stats')->assertUnauthorized();
        $this->getJson('/api/hr/departments')->assertUnauthorized();
        $this->getJson('/api/hr/attendances')->assertUnauthorized();
    }
}
