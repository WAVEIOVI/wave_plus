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
}
