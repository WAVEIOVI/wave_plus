<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveBalance;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveWorkflowAndBalancesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Employee $employee;

    private LeaveType $leaveType;

    private LeaveBalance $balance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        \App\Models\Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $dept = Department::create([
            'name' => 'Logistics',
            'code' => 'LOG',
        ]);

        $this->employee = Employee::create([
            'first_name' => 'Sophie',
            'last_name' => 'Laurent',
            'email' => 'sophie.laurent@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $dept->id,
            'status' => 'active',
            'user_id' => $this->user->id,
        ]);

        $this->leaveType = LeaveType::create([
            'name' => 'Paid Time Off',
            'code' => 'PTO',
            'max_days_per_year' => 20,
            'is_paid' => true,
            'requires_approval' => true,
        ]);

        $this->balance = LeaveBalance::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => 2026,
            'accrued_days' => 20,
            'approved_used_days' => 0,
            'pending_reserved_days' => 0,
            'available_days' => 20,
        ]);
    }

    public function test_leave_requester_can_view_only_their_own_balance(): void
    {
        $department = Department::create([
            'name' => 'Field Support',
            'code' => 'FS',
        ]);
        $requester = User::factory()->create();
        $requesterEmployee = Employee::create([
            'first_name' => 'Alex',
            'last_name' => 'Requester',
            'email' => 'alex.requester@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $department->id,
            'status' => 'active',
            'user_id' => $requester->id,
        ]);
        $otherEmployee = Employee::create([
            'first_name' => 'Sam',
            'last_name' => 'Colleague',
            'email' => 'sam.colleague@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $department->id,
            'status' => 'active',
        ]);
        foreach ([$requesterEmployee, $otherEmployee] as $employee) {
            LeaveBalance::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $this->leaveType->id,
                'year' => 2026,
                'accrued_days' => 20,
                'approved_used_days' => 0,
                'pending_reserved_days' => 0,
                'available_days' => 20,
            ]);
        }

        $role = \App\Models\Role::findOrCreate('Leave Requester', 'web');
        \App\Models\Permission::firstOrCreate(['name' => 'hr.leave.request', 'guard_name' => 'web']);
        $role->givePermissionTo('hr.leave.request');
        $requester->assignRole($role);
        Sanctum::actingAs($requester);

        $this->getJson('/api/hr/leave-types')->assertOk();
        $this->getJson('/api/hr/leave-balances')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $requesterEmployee->id);
        $this->getJson('/api/hr/leave-balances?employee_id='.$otherEmployee->id)->assertForbidden();
    }

    public function test_unrelated_hr_viewer_cannot_read_leave_balances_or_types(): void
    {
        $role = \App\Models\Role::findOrCreate('Employee Directory Viewer', 'web');
        \App\Models\Permission::firstOrCreate(['name' => 'hr.employees.view', 'guard_name' => 'web']);
        $role->givePermissionTo('hr.employees.view');

        $viewer = User::factory()->create();
        $viewer->assignRole($role);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/hr/leave-balances')->assertForbidden();
        $this->getJson('/api/hr/leave-types')->assertForbidden();
    }

    public function test_can_create_and_submit_leave_request_reserves_balance(): void
    {
        // 3 days leave request (total_days auto-calculated from July 1 to July 3)
        $response = $this->postJson('/api/hr/leave-requests', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'reason' => 'Summer vacation',
            'submit' => false,
        ]);

        $response->assertCreated();
        $requestId = $response->json('data.id');

        // Verify reference starts with LV-
        $this->assertStringStartsWith('LV-', $response->json('data.reference'));

        // Submit request (reserves days)
        $submitResponse = $this->postJson("/api/hr/leave-requests/{$requestId}/submit");
        $submitResponse->assertOk();

        // Check balance pending days updated to 3
        $this->balance->refresh();
        $this->assertEquals(3, (int) $this->balance->pending_reserved_days);
        $this->assertEquals(17, (int) $this->balance->available_days);
    }

    public function test_rejects_leave_request_when_insufficient_balance(): void
    {
        // Request 25 days when only 20 allocated
        $request = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-25',
            'total_days' => 25,
            'status' => 'draft',
        ]);

        $response = $this->postJson("/api/hr/leave-requests/{$request->id}/submit");
        $response->assertStatus(422);
    }

    public function test_approving_request_deducts_from_balance_and_marks_used(): void
    {
        $request = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-05',
            'total_days' => 5,
            'status' => 'draft',
        ]);

        // Submit to reserve
        $this->postJson("/api/hr/leave-requests/{$request->id}/submit")->assertOk();
        $this->assertEquals(5, (int) $this->balance->fresh()->pending_reserved_days);

        // Approve request
        $approveResponse = $this->postJson("/api/hr/leave-requests/{$request->id}/approve");
        $approveResponse->assertOk();

        // Verify request approved
        $this->assertEquals('approved', $request->fresh()->status);

        // Verify pending moved to used
        $freshBal = $this->balance->fresh();
        $this->assertEquals(0, (int) $freshBal->pending_reserved_days);
        $this->assertEquals(5, (int) $freshBal->approved_used_days);
        $this->assertEquals(15, (int) $freshBal->available_days);
    }

    public function test_rejecting_request_releases_reserved_days(): void
    {
        $request = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-04',
            'total_days' => 4,
            'status' => 'draft',
        ]);

        $this->postJson("/api/hr/leave-requests/{$request->id}/submit")->assertOk();
        $this->assertEquals(4, (int) $this->balance->fresh()->pending_reserved_days);

        // Reject request
        $this->postJson("/api/hr/leave-requests/{$request->id}/reject", [
            'rejection_reason' => 'High operational demand during this window',
        ])->assertOk();

        $freshBal = $this->balance->fresh();
        $this->assertEquals(0, (int) $freshBal->pending_reserved_days);
        $this->assertEquals(0, (int) $freshBal->approved_used_days);
        $this->assertEquals(20, (int) $freshBal->available_days);
    }

    public function test_overlapping_full_day_request_is_rejected(): void
    {
        LeaveRequest::create([
            'reference' => 'LV-00010',
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-08',
            'total_days' => 4,
            'status' => 'submitted',
        ]);

        // Attempt overlapping request (Oct 7 - Oct 10)
        $res = $this->postJson('/api/hr/leave-requests', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-10',
            'submit' => false,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['start_date']);
    }

    public function test_overlapping_hourly_request_with_intersecting_times_is_rejected(): void
    {
        $this->leaveType->update(['allows_hourly' => true]);

        LeaveRequest::create([
            'reference' => 'LV-00011',
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-15',
            'duration_type' => 'hourly',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'total_days' => 0.5,
            'status' => 'submitted',
        ]);

        // Intersecting hourly request: 10:00 - 14:00 on the same date
        $res = $this->postJson('/api/hr/leave-requests', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'duration_type' => 'hourly',
            'start_date' => '2026-10-15',
            'start_time' => '10:00',
            'end_time' => '14:00',
            'submit' => false,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['start_date']);
    }

    public function test_non_overlapping_hourly_requests_on_same_day_are_allowed(): void
    {
        $this->leaveType->update(['allows_hourly' => true]);

        // Morning hourly leave: 08:00 - 10:00
        $res1 = $this->postJson('/api/hr/leave-requests', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'duration_type' => 'hourly',
            'start_date' => '2026-10-15',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'submit' => false,
        ]);
        $res1->assertCreated();

        // Afternoon hourly leave: 14:00 - 16:00 (non-overlapping)
        $res2 = $this->postJson('/api/hr/leave-requests', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'duration_type' => 'hourly',
            'start_date' => '2026-10-15',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'submit' => false,
        ]);
        $res2->assertCreated();
    }

    public function test_hourly_leave_rejected_if_leave_type_disallows_hourly(): void
    {
        $this->leaveType->update(['allows_hourly' => false]);

        $res = $this->postJson('/api/hr/leave-requests', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'duration_type' => 'hourly',
            'start_date' => '2026-10-15',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'submit' => false,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['duration_type']);
    }

    public function test_employee_self_service_can_only_request_leave_for_self(): void
    {
        $colleague = Employee::create([
            'first_name' => 'Kais',
            'last_name' => 'Ben Amor',
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);

        // Self-service user with only hr.leave.request
        $selfUser = User::factory()->create();
        $this->employee->update(['user_id' => $selfUser->id]);
        $role = \App\Models\Role::firstOrCreate(['name' => 'Employee Self-Service', 'guard_name' => 'web']);
        \App\Models\Permission::firstOrCreate(['name' => 'hr.leave.request', 'guard_name' => 'web']);
        $role->givePermissionTo('hr.leave.request');
        $selfUser->assignRole($role);
        Sanctum::actingAs($selfUser);

        // Submitting for colleague -> Rejected with 422 validation error
        $res = $this->postJson('/api/hr/leave-requests', [
            'employee_id' => $colleague->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-22',
            'submit' => false,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['employee_id']);
    }

    public function test_manager_cannot_approve_own_leave_request(): void
    {
        $managerUser = User::factory()->create();
        $managerEmp = Employee::create([
            'first_name' => 'Nadia',
            'last_name' => 'Manager',
            'hire_date' => '2026-01-01',
            'status' => 'active',
            'user_id' => $managerUser->id,
        ]);
        LeaveBalance::create([
            'employee_id' => $managerEmp->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => 2026,
            'accrued_days' => 20,
            'available_days' => 20,
        ]);

        $role = \App\Models\Role::firstOrCreate(['name' => 'HR Approver', 'guard_name' => 'web']);
        \App\Models\Permission::firstOrCreate(['name' => 'hr.leave.request', 'guard_name' => 'web']);
        \App\Models\Permission::firstOrCreate(['name' => 'hr.leave.approve', 'guard_name' => 'web']);
        $role->givePermissionTo(['hr.leave.request', 'hr.leave.approve']);
        $managerUser->assignRole($role);
        Sanctum::actingAs($managerUser);

        // Manager creates own leave request
        $reqRes = $this->postJson('/api/hr/leave-requests', [
            'employee_id' => $managerEmp->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-11-16',
            'end_date' => '2026-11-18',
            'submit' => true,
        ]);
        $reqRes->assertCreated();
        $requestId = $reqRes->json('data.id');

        // Manager tries to approve own request -> 403 Forbidden
        $approveRes = $this->postJson("/api/hr/leave-requests/{$requestId}/approve");
        $approveRes->assertStatus(403);
    }

    public function test_approved_leave_synchronizes_coverage_without_deleting_schedules(): void
    {
        $shift = \App\Modules\Hr\Models\Shift::create([
            'name' => 'Standard Day',
            'code' => 'STD-DAY',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_duration_minutes' => 60,
        ]);

        $schedule = \App\Modules\Hr\Models\Schedule::create([
            'employee_id' => $this->employee->id,
            'shift_id' => $shift->id,
            'date' => '2026-11-10',
            'custom_start_time' => '08:00:00',
            'custom_end_time' => '17:00:00',
            'lifecycle_status' => 'planned',
            'coverage_status' => 'normal',
        ]);

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-10',
            'total_days' => 1,
            'status' => 'submitted',
        ]);
        $this->balance->update(['pending_reserved_days' => 1, 'available_days' => 19]);

        // Approve leave
        $this->postJson("/api/hr/leave-requests/{$leave->id}/approve", [
            'decision_note' => 'Approved by Director',
        ])->assertOk();

        // Verify schedule still exists, start/end time preserved, only coverage status updated
        $this->assertDatabaseHas('hr_schedules', [
            'id' => $schedule->id,
            'custom_start_time' => '08:00:00',
            'custom_end_time' => '17:00:00',
            'coverage_status' => 'full_day_leave',
        ]);

        $this->assertEquals('Approved by Director', $leave->fresh()->decision_note);
    }

    public function test_cancelled_leave_restores_coverage_and_recalculates_attendance(): void
    {
        $shift = \App\Modules\Hr\Models\Shift::create([
            'name' => 'Standard Day 2',
            'code' => 'STD-DAY-2',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_duration_minutes' => 60,
        ]);

        $schedule = \App\Modules\Hr\Models\Schedule::create([
            'employee_id' => $this->employee->id,
            'shift_id' => $shift->id,
            'date' => '2026-11-12',
            'custom_start_time' => '08:00:00',
            'custom_end_time' => '17:00:00',
            'lifecycle_status' => 'planned',
            'coverage_status' => 'normal',
        ]);

        $att = \App\Modules\Hr\Models\Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-11-12',
            'scheduled_in' => '08:00:00',
            'scheduled_out' => '17:00:00',
            'status' => 'present',
        ]);

        $leave = LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-12',
            'total_days' => 1,
            'status' => 'submitted',
        ]);
        $this->balance->update(['pending_reserved_days' => 1, 'available_days' => 19]);

        $this->postJson("/api/hr/leave-requests/{$leave->id}/approve")->assertOk();
        $this->assertEquals('full_day_leave', $schedule->fresh()->coverage_status);
        $this->assertEquals($leave->id, $att->fresh()->covering_leave_id);

        // Cancel approved leave
        $this->postJson("/api/hr/leave-requests/{$leave->id}/cancel")->assertOk();

        // Coverage restored to normal and covering_leave_id cleared
        $this->assertEquals('normal', $schedule->fresh()->coverage_status);
        $this->assertNull($att->fresh()->covering_leave_id);
        $this->assertEquals(0, (int) $this->balance->fresh()->approved_used_days);
        $this->assertEquals(20, (int) $this->balance->fresh()->available_days);
    }

    public function test_leave_balance_adjustment_records_audit_log(): void
    {
        $role = \App\Models\Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'web']);
        \App\Models\Permission::firstOrCreate(['name' => 'hr.leave.manage', 'guard_name' => 'web']);
        $role->givePermissionTo('hr.leave.manage');
        $this->user->assignRole($role);

        $res = $this->postJson('/api/hr/leave-balances/adjust', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'year' => 2026,
            'accrued_days' => 25,
            'notes' => 'Seniority bonus days granted',
        ]);

        $res->assertOk();
        $this->assertEquals(25, (int) $this->balance->fresh()->accrued_days);
        $this->assertEquals(25, (int) $this->balance->fresh()->available_days);

        $this->assertDatabaseHas('hr_audit_logs', [
            'auditable_type' => LeaveBalance::class,
            'auditable_id' => $this->balance->id,
            'action' => 'LEAVE_BALANCE_ADJUSTED',
            'reason' => 'Seniority bonus days granted',
        ]);
    }

    public function test_cannot_delete_leave_type_with_active_balances_or_requests(): void
    {
        $role = \App\Models\Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'web']);
        \App\Models\Permission::firstOrCreate(['name' => 'hr.leave.manage', 'guard_name' => 'web']);
        $role->givePermissionTo('hr.leave.manage');
        $this->user->assignRole($role);

        // $this->leaveType is referenced by $this->balance
        $res = $this->deleteJson("/api/hr/leave-types/{$this->leaveType->id}");
        $res->assertStatus(422);

        $this->assertDatabaseHas('hr_leave_types', [
            'id' => $this->leaveType->id,
        ]);
    }
}
