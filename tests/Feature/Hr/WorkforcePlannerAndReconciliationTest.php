<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Models\AttendanceConfig;
use App\Modules\Hr\Models\AttendanceException;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Models\Schedule;
use App\Modules\Hr\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforcePlannerAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Employee $employee;

    protected Shift $shift;

    protected LeaveType $leaveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        \App\Models\Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        \Laravel\Sanctum\Sanctum::actingAs($this->user);

        $dept = Department::create(['name' => 'Operations', 'code' => 'OPS', 'is_active' => true]);
        $pos = Position::create(['title' => 'Field Tech', 'code' => 'TECH-01', 'department_id' => $dept->id, 'is_active' => true]);

        $this->employee = Employee::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'work_email' => 'jane.doe@example.com',
            'department_id' => $dept->id,
            'current_position_id' => $pos->id,
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->shift = Shift::create([
            'name' => 'Day Shift',
            'code' => 'DS1',
            'start_time' => '08:00:00',
            'end_time' => '16:30:00',
            'break_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->leaveType = LeaveType::create([
            'code' => 'ANNUAL',
            'name' => 'Annual Vacation',
            'is_paid' => true,
            'max_days_per_year' => 20.0,
            'requires_approval' => true,
            'allows_hourly' => true,
            'is_active' => true,
        ]);

        AttendanceConfig::create([
            'scope' => 'global',
            'early_checkin_grace_minutes' => 15,
            'late_checkin_grace_minutes' => 15,
            'early_checkout_grace_minutes' => 15,
            'late_checkout_grace_minutes' => 15,
            'hourly_leave_tolerance_minutes' => 15,
            'min_hourly_leave_unit_minutes' => 30,
        ]);
    }

    public function test_attendance_viewer_cannot_review_attendance_exceptions(): void
    {
        $exception = AttendanceException::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-10-01',
            'exception_type' => 'late_arrival',
            'review_status' => AttendanceException::REVIEW_OPEN,
        ]);

        $role = \App\Models\Role::findOrCreate('Attendance Viewer', 'web');
        \App\Models\Permission::findOrCreate('hr.attendance.view', 'web');
        $role->givePermissionTo('hr.attendance.view');

        $viewer = User::factory()->create();
        $viewer->assignRole($role);
        \Laravel\Sanctum\Sanctum::actingAs($viewer);

        $this->postJson("/api/hr/attendance-exceptions/{$exception->id}/resolve")
            ->assertForbidden();

        $this->assertSame(AttendanceException::REVIEW_OPEN, $exception->fresh()->review_status);
    }

    public function test_hr_read_permissions_are_scoped_and_do_not_grant_mutations(): void
    {
        $role = \App\Models\Role::findOrCreate('Employee Viewer', 'web');
        \App\Models\Permission::findOrCreate('hr.employees.view', 'web');
        $role->givePermissionTo('hr.employees.view');

        $viewer = User::factory()->create();
        $viewer->assignRole($role);
        \Laravel\Sanctum\Sanctum::actingAs($viewer);

        $this->getJson('/api/hr/attendance-exceptions')->assertForbidden();
        $this->getJson('/api/hr/workforce/planner-grid')->assertForbidden();
        $this->getJson('/api/hr/workforce/shifts')->assertForbidden();
        $this->postJson('/api/hr/workforce/query-availability', [
            'start_time' => '2026-10-01 08:00:00',
            'end_time' => '2026-10-01 16:00:00',
        ])->assertForbidden();

        $this->getJson('/api/hr/appraisals/cycles')->assertOk();
        $this->getJson('/api/hr/appraisals')->assertOk();
        $this->postJson('/api/hr/appraisals/cycles', [])->assertForbidden();
        $this->postJson('/api/hr/appraisals', [])->assertForbidden();
    }

    public function test_workforce_viewer_can_read_but_cannot_change_workforce_resources(): void
    {
        $role = \App\Models\Role::findOrCreate('Workforce Viewer', 'web');
        \App\Models\Permission::findOrCreate('hr.workforce.view', 'web');
        $role->givePermissionTo('hr.workforce.view');

        $viewer = User::factory()->create();
        $viewer->assignRole($role);
        \Laravel\Sanctum\Sanctum::actingAs($viewer);

        $this->getJson('/api/hr/workforce/planner-grid')->assertOk();
        $this->getJson('/api/hr/workforce/shifts')->assertOk();
        $this->postJson('/api/hr/workforce/shifts', [])->assertForbidden();
        $this->postJson('/api/hr/workforce/bulk-schedule', [])->assertForbidden();
    }

    public function test_planner_grid_returns_structured_matrix(): void
    {
        // Create schedule
        Schedule::create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date' => '2026-09-28',
            'lifecycle_status' => 'planned',
            'coverage_status' => 'normal',
        ]);

        $response = $this->getJson('/api/hr/workforce/planner-grid?start_date=2026-09-28&end_date=2026-10-04');

        $response->assertOk();
        $response->assertJsonStructure([
            'dates',
            'shifts',
            'employees' => [
                '*' => [
                    'id',
                    'full_name',
                    'days',
                ],
            ],
            'meta',
        ]);

        $empData = collect($response->json('employees'))->firstWhere('id', $this->employee->id);
        $this->assertNotNull($empData);
        $this->assertEquals('DS1', $empData['days']['2026-09-28']['schedule']['shift_code']);
    }

    public function test_bulk_schedule_creation(): void
    {
        $response = $this->postJson('/api/hr/workforce/bulk-schedule', [
            'employee_ids' => [$this->employee->id],
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-09',
            'shift_id' => $this->shift->id,
            'days_of_week' => [1, 2, 3, 4, 5],
            'lifecycle_status' => 'planned',
        ]);

        $response->assertCreated();
        $this->assertEquals(5, $response->json('data.created'));

        $this->assertEquals(5, Schedule::where('employee_id', $this->employee->id)
            ->whereDate('date', '>=', '2026-10-05')
            ->whereDate('date', '<=', '2026-10-09')
            ->count());
    }

    public function test_hourly_leave_request_and_schedule_coverage_reconciliation(): void
    {
        // 1. Create a planned schedule
        Schedule::create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date' => '2026-09-30',
            'lifecycle_status' => 'planned',
            'coverage_status' => 'normal',
        ]);

        // 2. Submit hourly leave request (2 hours)
        $leaveRes = $this->postJson('/api/hr/leave-requests', [
            'employee_id' => $this->employee->id,
            'leave_type_id' => $this->leaveType->id,
            'duration_type' => 'hourly',
            'start_date' => '2026-09-30',
            'end_date' => '2026-09-30',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'total_days' => 0.25,
            'reason' => 'Dentist appointment',
            'submit' => true,
        ]);

        $leaveRes->assertCreated();
        $leaveId = $leaveRes->json('data.id');

        // 3. Approve leave request
        $approveRes = $this->postJson("/api/hr/leave-requests/{$leaveId}/approve", [
            'decision_note' => 'Approved by supervisor',
        ]);
        $approveRes->assertOk();

        // 4. Verify schedule coverage reconciled to partial_leave
        $schedule = Schedule::where('employee_id', $this->employee->id)
            ->whereDate('date', '2026-09-30')
            ->first();

        $this->assertEquals(Schedule::COVERAGE_PARTIAL_LEAVE, $schedule->coverage_status);
    }

    public function test_late_clock_in_generates_reconciliation_exception(): void
    {
        // Shift starts at 08:00
        Schedule::create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date' => '2026-09-29',
            'lifecycle_status' => 'planned',
            'coverage_status' => 'normal',
        ]);

        // Clock in at 08:35 (20 minutes past 15min grace buffer)
        $clockRes = $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'event_time' => '2026-09-29 08:35:00',
        ]);
        $clockRes->assertCreated();

        // Verify exception created
        $exception = AttendanceException::where('employee_id', $this->employee->id)
            ->whereDate('date', '2026-09-29')
            ->first();

        $this->assertNotNull($exception);
        $this->assertEquals('late_arrival', $exception->exception_type);
        $this->assertEquals(35, $exception->deviation_minutes);
        $this->assertEquals('open', $exception->review_status);

        // Test exception acknowledge and resolution
        $ackRes = $this->postJson("/api/hr/attendance-exceptions/{$exception->id}/acknowledge");
        $ackRes->assertOk();
        $this->assertEquals('acknowledged', $exception->fresh()->review_status);

        $resolveRes = $this->postJson("/api/hr/attendance-exceptions/{$exception->id}/resolve", [
            'notes' => 'Tolerated due to train cancellation',
        ]);
        $resolveRes->assertOk();
        $this->assertEquals('resolved', $exception->fresh()->review_status);
    }

    /**
     * Frontend shift-manager contract: name + optional code (auto-derived) +
     * break_duration_minutes + is_overnight. Locks the validation contract
     * used by the Workforce > Shifts tab.
     */
    public function test_shift_crud_via_api(): void
    {
        $createRes = $this->postJson('/api/hr/workforce/shifts', [
            'name' => 'Night Shift',
            'code' => 'NS1',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'break_duration_minutes' => 45,
            'is_overnight' => true,
            'is_active' => true,
        ]);
        $createRes->assertCreated();
        $shiftId = $createRes->json('data.id') ?? $createRes->json('data.shift.id');
        $this->assertNotNull($shiftId);

        $this->assertDatabaseHas('hr_shifts', [
            'id' => $shiftId,
            'code' => 'NS1',
            'is_overnight' => true,
            'break_duration_minutes' => 45,
        ]);

        $updateRes = $this->putJson("/api/hr/workforce/shifts/{$shiftId}", [
            'break_duration_minutes' => 30,
        ]);
        $updateRes->assertOk();

        $this->assertEquals(30, Shift::find($shiftId)->break_duration_minutes);

        $this->deleteJson("/api/hr/workforce/shifts/{$shiftId}")->assertOk();
        $this->assertDatabaseMissing('hr_shifts', ['id' => $shiftId]);

        // Validation: duplicate code rejected
        $this->postJson('/api/hr/workforce/shifts', [
            'name' => 'Shift A', 'code' => 'DS1', 'start_time' => '07:00', 'end_time' => '15:00',
        ])->assertStatus(422);
    }
}
