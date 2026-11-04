<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Models\Attendance;
use App\Modules\Hr\Models\AttendanceCorrection;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimeClockAndAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Employee $employee;
    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        \App\Models\Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $dept = Department::create([
            'name' => 'Operations',
            'code' => 'OPS',
        ]);

        $this->shift = Shift::create([
            'name' => 'Morning Shift',
            'code' => 'MS-01',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break_minutes' => 60,
            'is_overtime_eligible' => true,
        ]);

        $this->employee = Employee::create([
            'first_name' => 'Thomas',
            'last_name' => 'Benoit',
            'email' => 'thomas.benoit@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $dept->id,
            'current_shift_id' => $this->shift->id,
            'status' => 'active',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_clock_in_and_creates_raw_event_and_attendance(): void
    {
        $response = $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'timestamp' => '2026-09-13 08:05:00',
            'source' => 'web',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('event.event_type', 'in');

        // Check raw event created
        $this->assertDatabaseHas('hr_time_clock_events', [
            'employee_id' => $this->employee->id,
            'event_type' => 'in',
        ]);

        // Check daily attendance record created
        $attendance = Attendance::where('employee_id', $this->employee->id)
            ->whereDate('date', '2026-09-13')
            ->first();
        $this->assertNotNull($attendance);
        $this->assertEquals('present', $attendance->status);
    }

    public function test_rejects_duplicate_clock_in_without_clock_out(): void
    {
        // First clock in
        $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'timestamp' => '2026-09-13 08:00:00',
        ])->assertCreated();

        // Second clock in should fail with 422
        $response = $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'timestamp' => '2026-09-13 08:10:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_clock_out_calculates_worked_hours(): void
    {
        // Clock In at 08:00
        $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'timestamp' => '2026-09-13 08:00:00',
        ])->assertCreated();

        // Clock Out at 16:30
        $outResponse = $this->postJson('/api/hr/time-clock/clock-out', [
            'employee_id' => $this->employee->id,
            'timestamp' => '2026-09-13 16:30:00',
        ]);

        $outResponse->assertCreated();

        $attendance = Attendance::where('employee_id', $this->employee->id)
            ->whereDate('date', '2026-09-13')
            ->first();

        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->actual_out);
        $this->assertGreaterThan(0, $attendance->worked_hours);
    }

    public function test_attendance_correction_workflow(): void
    {
        // Existing attendance with missed clock out
        $att = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-13',
            'actual_in' => '08:00:00',
            'actual_out' => null,
            'status' => 'present',
            'worked_hours' => 0,
        ]);

        // Submit correction
        $corrResponse = $this->postJson("/api/hr/attendances/{$att->id}/request-correction", [
            'requested_actual_in' => '08:00:00',
            'requested_actual_out' => '16:00:00',
            'reason' => 'Forgot to punch out before leaving',
        ]);

        $corrResponse->assertCreated();
        $correctionId = $corrResponse->json('data.id');

        // Approve correction
        $decideResponse = $this->postJson("/api/hr/attendance-corrections/{$correctionId}/decide", [
            'approved' => true,
            'decision_reason' => 'Confirmed by supervisor',
        ]);

        $decideResponse->assertOk();

        // Verify correction status is approved and attendance updated
        $this->assertDatabaseHas('hr_attendance_corrections', [
            'id' => $correctionId,
            'status' => 'approved',
        ]);

        $freshAtt = $att->fresh();
        $this->assertNotNull($freshAtt->actual_out);
        $this->assertGreaterThan(0, $freshAtt->worked_hours);
    }

    public function test_badge_endpoint_returns_a_scannable_qr_payload(): void
    {
        $response = $this->getJson("/api/hr/time-clock/badge/{$this->employee->id}");

        $response->assertOk();
        $response->assertJsonPath('data.employee_id', $this->employee->id);
        $response->assertJsonPath('data.employee_number', $this->employee->employee_number);
        $response->assertJsonPath('data.qr_payload', 'WP:EMP:'.$this->employee->employee_number);
    }

    public function test_scanning_a_badge_toggles_clock_in_then_clock_out(): void
    {
        $badge = 'WP:EMP:'.$this->employee->employee_number;

        $in = $this->postJson('/api/hr/time-clock/scan', [
            'badge' => $badge,
            'event_time' => '2026-10-01 08:02:00',
        ]);

        $in->assertCreated();
        $in->assertJsonPath('action', 'in');
        $in->assertJsonPath('employee.employee_number', $this->employee->employee_number);

        $this->assertDatabaseHas('hr_time_clock_events', [
            'employee_id' => $this->employee->id,
            'event_type' => 'in',
            'source' => 'qr',
        ]);

        $out = $this->postJson('/api/hr/time-clock/scan', [
            'badge' => $badge,
            'event_time' => '2026-10-01 16:30:00',
        ]);

        $out->assertCreated();
        $out->assertJsonPath('action', 'out');

        $attendance = Attendance::where('employee_id', $this->employee->id)
            ->whereDate('date', '2026-10-01')
            ->first();

        $this->assertNotNull($attendance);
        $this->assertGreaterThan(0, $attendance->worked_hours);
    }

    public function test_scanning_accepts_a_bare_employee_number(): void
    {
        $response = $this->postJson('/api/hr/time-clock/scan', [
            'badge' => $this->employee->employee_number,
            'event_time' => '2026-10-03 09:00:00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('action', 'in');
    }

    public function test_scanning_accepts_a_numeric_pin_fallback(): void
    {
        // employee_number EMP-00001 → the PIN keypad types "1".
        $response = $this->postJson('/api/hr/time-clock/scan', [
            'badge' => '1',
            'event_time' => '2026-10-04 09:00:00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('action', 'in');
        $response->assertJsonPath('employee.employee_number', $this->employee->employee_number);
    }

    public function test_scanning_an_unknown_badge_returns_not_found(): void
    {
        $this->postJson('/api/hr/time-clock/scan', ['badge' => 'WP:EMP:NOPE-99999'])
            ->assertNotFound();
    }

    public function test_bulk_punch_records_a_crew_and_skips_duplicates(): void
    {
        $second = Employee::create([
            'first_name' => 'Amira',
            'last_name' => 'Khelifi',
            'hire_date' => '2026-02-01',
            'status' => 'active',
        ]);

        $crew = [$this->employee->id, $second->id];

        $first = $this->postJson('/api/hr/time-clock/bulk', [
            'employee_ids' => $crew,
            'event_type' => 'in',
            'event_time' => '2026-10-05 07:55:00',
        ]);

        $first->assertCreated();
        $first->assertJsonPath('recorded', 2);
        $first->assertJsonPath('failed', 0);

        // Re-running the same crew clock-in is skipped by the duplicate guard.
        $again = $this->postJson('/api/hr/time-clock/bulk', [
            'employee_ids' => $crew,
            'event_type' => 'in',
            'event_time' => '2026-10-05 08:05:00',
        ]);

        $again->assertCreated();
        $again->assertJsonPath('recorded', 0);
        $again->assertJsonPath('failed', 2);
    }

    public function test_multiple_in_out_events_accumulates_worked_hours_accurately(): void
    {
        $date = '2026-11-04';

        // 1. Clock in morning
        $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'event_time' => "{$date} 08:00:00",
        ])->assertCreated();

        // 2. Clock out lunch
        $this->postJson('/api/hr/time-clock/clock-out', [
            'employee_id' => $this->employee->id,
            'event_time' => "{$date} 12:00:00",
        ])->assertCreated();

        $attLunch = Attendance::where('employee_id', $this->employee->id)->whereDate('date', $date)->first();
        $this->assertEquals(4.0, $attLunch->worked_hours);
        $this->assertEquals('12:00:00', $attLunch->actual_out);

        // 3. Clock back in afternoon
        $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'event_time' => "{$date} 13:00:00",
        ])->assertCreated();

        $attAfternoon = Attendance::where('employee_id', $this->employee->id)->whereDate('date', $date)->first();
        // While actively working afternoon session, actual_out should be null
        $this->assertNull($attAfternoon->actual_out);

        // 4. Clock out end of day
        $this->postJson('/api/hr/time-clock/clock-out', [
            'employee_id' => $this->employee->id,
            'event_time' => "{$date} 17:00:00",
        ])->assertCreated();

        $attFinal = Attendance::where('employee_id', $this->employee->id)->whereDate('date', $date)->first();
        $this->assertEquals(8.0, $attFinal->worked_hours);
        $this->assertEquals('08:00:00', $attFinal->actual_in);
        $this->assertEquals('17:00:00', $attFinal->actual_out);
    }

    public function test_planned_working_time_deducts_shift_break_duration(): void
    {
        $shiftWithBreak = Shift::create([
            'name' => 'Standard Winter Day',
            'code' => 'WIN-STD',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_duration_minutes' => 60,
            'is_active' => true,
        ]);

        \App\Modules\Hr\Models\Schedule::create([
            'employee_id' => $this->employee->id,
            'shift_id' => $shiftWithBreak->id,
            'date' => '2026-11-05',
            'status' => 'scheduled',
            'lifecycle_status' => 'planned',
        ]);

        $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'event_time' => '2026-11-05 08:00:00',
        ])->assertCreated();

        $att = Attendance::where('employee_id', $this->employee->id)->whereDate('date', '2026-11-05')->first();
        $this->assertNotNull($att);
        // 9 hours total span - 60 min break = 8.0 planned working hours
        $this->assertEquals(8.0, $att->scheduled_hours);
    }

    public function test_overnight_shift_cross_midnight_punch_pairing(): void
    {
        $nightShift = Shift::create([
            'name' => 'Night Shift',
            'code' => 'NS-OVER',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'is_overnight' => true,
            'break_duration_minutes' => 0,
            'is_active' => true,
        ]);

        \App\Modules\Hr\Models\Schedule::create([
            'employee_id' => $this->employee->id,
            'shift_id' => $nightShift->id,
            'date' => '2026-11-09',
            'status' => 'scheduled',
            'lifecycle_status' => 'planned',
        ]);

        // Monday 22:00 clock in
        $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'event_time' => '2026-11-09 22:00:00',
        ])->assertCreated();

        // Tuesday 06:00 clock out
        $outRes = $this->postJson('/api/hr/time-clock/scan', [
            'badge' => $this->employee->employee_number,
            'event_time' => '2026-11-10 06:00:00',
        ]);
        $outRes->assertCreated();
        $outRes->assertJsonPath('action', 'out');

        // Monday attendance record updated with full 8 hours worked
        $attMon = Attendance::where('employee_id', $this->employee->id)->whereDate('date', '2026-11-09')->first();
        $this->assertNotNull($attMon);
        $this->assertEquals(8.0, $attMon->worked_hours);
        $this->assertEquals('22:00:00', $attMon->actual_in);
        $this->assertEquals('06:00:00', $attMon->actual_out);
        $this->assertEquals(0, $attMon->late_minutes);
        $this->assertEquals(0, $attMon->early_departure_minutes);
    }

    public function test_approved_hourly_leave_adjusts_expected_arrival_and_planned_hours(): void
    {
        $leaveType = \App\Modules\Hr\Models\LeaveType::create([
            'code' => 'MED',
            'name' => 'Medical Hourly',
            'is_paid' => true,
            'max_days_per_year' => 5.0,
            'allows_hourly' => true,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $dayShift = Shift::create([
            'name' => 'Full Day',
            'code' => 'FD-01',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_duration_minutes' => 60,
            'is_active' => true,
        ]);

        \App\Modules\Hr\Models\Schedule::create([
            'employee_id' => $this->employee->id,
            'shift_id' => $dayShift->id,
            'date' => '2026-11-12',
            'status' => 'scheduled',
            'lifecycle_status' => 'planned',
        ]);

        // Approved medical leave from 08:00 to 10:00 (2 hours)
        \App\Modules\Hr\Models\LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-11-12',
            'end_date' => '2026-11-12',
            'duration_type' => 'hourly',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'total_days' => 0.25,
            'status' => 'approved',
            'reason' => 'Doctor appointment',
        ]);

        // Employee clocks in at 10:05 (5 min after leave end, well within 15 min tolerance)
        $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'event_time' => '2026-11-12 10:05:00',
        ])->assertCreated();

        $att = Attendance::where('employee_id', $this->employee->id)->whereDate('date', '2026-11-12')->first();
        $this->assertNotNull($att);
        // Scheduled net: 8h - 2h leave = 6.0 planned hours
        $this->assertEquals(6.0, $att->scheduled_hours);
        $this->assertEquals(5, $att->late_minutes);
        // Since 5m <= 15m tolerance, status should remain present (not late)
        $this->assertEquals('present', $att->status);

        // No late arrival exception should be generated
        $hasLateException = \App\Modules\Hr\Models\AttendanceException::where('employee_id', $this->employee->id)
            ->whereDate('date', '2026-11-12')
            ->where('exception_type', 'late_arrival')
            ->exists();
        $this->assertFalse($hasLateException);
    }

    public function test_inactive_or_terminated_employee_cannot_punch(): void
    {
        $this->employee->update(['status' => 'terminated']);

        $res = $this->postJson('/api/hr/time-clock/scan', [
            'badge' => $this->employee->employee_number,
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('message', "Employee {$this->employee->full_name} is terminated and cannot record attendance.");
    }

    public function test_attendance_correction_records_audit_trail_and_prevents_self_approval(): void
    {
        $managerUser = User::factory()->create();
        $officerRole = \App\Models\Role::findOrCreate('HR Officer', 'web');
        $managerRole = \App\Models\Role::findOrCreate('HR Manager', 'web');
        \App\Models\Permission::findOrCreate('hr.attendance.correct', 'web');
        \App\Models\Permission::findOrCreate('hr.attendance.manage', 'web');
        \App\Models\Permission::findOrCreate('hr.attendance.view', 'web');
        $officerRole->givePermissionTo(['hr.attendance.correct', 'hr.attendance.view']);
        $managerRole->givePermissionTo(['hr.attendance.correct', 'hr.attendance.manage', 'hr.attendance.view']);

        $officerUser = User::factory()->create();
        $officerUser->assignRole($officerRole);
        $managerUser->assignRole($managerRole);

        $att = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-11-02',
            'scheduled_in' => '08:00:00',
            'scheduled_out' => '17:00:00',
            'actual_in' => '08:30:00',
            'actual_out' => '17:00:00',
            'scheduled_hours' => 8.0,
            'worked_hours' => 8.5,
            'status' => 'late',
        ]);

        // 1. Officer requests correction
        Sanctum::actingAs($officerUser);
        $reqRes = $this->postJson("/api/hr/attendances/{$att->id}/request-correction", [
            'requested_actual_in' => '08:00:00',
            'reason' => 'Badge scanner was offline at turnstile',
        ]);
        $reqRes->assertCreated();
        $correctionId = $reqRes->json('data.id');

        // Audit log created for request
        $this->assertDatabaseHas('hr_audit_logs', [
            'auditable_id' => $att->id,
            'actor_id' => $officerUser->id,
            'action' => 'attendance_correction_requested',
        ]);

        // 2. Requester tries to approve own correction -> Rejected with 403 Forbidden
        $selfApprove = $this->postJson("/api/hr/attendance-corrections/{$correctionId}/decide", [
            'approved' => true,
            'decision_reason' => 'Self-approving',
        ]);
        $selfApprove->assertStatus(403);

        // 3. Independent manager approves correction -> Success (200)
        Sanctum::actingAs($managerUser);
        $approveRes = $this->postJson("/api/hr/attendance-corrections/{$correctionId}/decide", [
            'approved' => true,
            'decision_reason' => 'Turnstile log verified',
        ]);
        $approveRes->assertOk();

        // Audit log created for decision
        $this->assertDatabaseHas('hr_audit_logs', [
            'auditable_id' => $att->id,
            'actor_id' => $managerUser->id,
            'action' => 'attendance_correction_approved',
        ]);

        $this->assertEquals('08:00:00', $att->fresh()->actual_in);
        $this->assertTrue($att->fresh()->is_corrected);
    }

    public function test_employee_self_service_can_only_view_own_timeline(): void
    {
        $secondEmp = Employee::create([
            'first_name' => 'Karim',
            'last_name' => 'Trabelsi',
            'hire_date' => '2026-03-01',
            'status' => 'active',
        ]);

        // Create time clock events for both employees
        \App\Modules\Hr\Models\TimeClockEvent::create([
            'employee_id' => $this->employee->id,
            'event_type' => 'in',
            'event_time' => '2026-11-03 08:00:00',
        ]);
        \App\Modules\Hr\Models\TimeClockEvent::create([
            'employee_id' => $secondEmp->id,
            'event_type' => 'in',
            'event_time' => '2026-11-03 08:15:00',
        ]);

        // Act as self-service employee (no hr.attendance.view permission)
        $selfUser = User::factory()->create();
        $this->employee->update(['user_id' => $selfUser->id]);
        Sanctum::actingAs($selfUser);

        $res = $this->getJson('/api/hr/attendances/timeline?date=2026-11-03');
        $res->assertOk();

        $returnedEmployeeIds = collect($res->json('data'))->pluck('employee.id')->all();
        $this->assertContains($this->employee->id, $returnedEmployeeIds);
        $this->assertNotContains($secondEmp->id, $returnedEmployeeIds);
    }
}
