<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\Attendance;
use App\Modules\Hr\Models\AttendanceConfig;
use App\Modules\Hr\Models\AttendanceCorrection;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Schedule;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\TimeClockEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Attendance & time-clock workflow coverage:
 * stats aggregates, self-service clock authorization, correction contract,
 * multiple punches per day, grace tolerance, approved-leave forcing and UI reload.
 */
class AttendanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Employee $employee;
    private Department $dept;
    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        $this->user = User::factory()->create();
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->dept = Department::create(['name' => 'Field Ops', 'code' => 'FIELDOPS']);
        $this->shift = Shift::create([
            'name' => 'Day Shift',
            'code' => 'DAY-08',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
        ]);

        $this->employee = Employee::create([
            'first_name' => 'Mehdi',
            'last_name' => 'Karim',
            'work_email' => 'mehdi.karim@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
            'user_id' => $this->user->id,
        ]);

        AttendanceConfig::create([
            'scope' => 'global', 'scope_id' => null,
            'early_checkin_grace_minutes' => 30,
            'late_checkin_grace_minutes' => 15,
            'early_checkout_grace_minutes' => 15,
            'late_checkout_grace_minutes' => 30,
            'hourly_leave_tolerance_minutes' => 30,
            'min_hourly_leave_unit_minutes' => 60,
        ]);
    }

    private function scheduleFor(string $date, ?Shift $shift = null): Schedule
    {
        return Schedule::create([
            'employee_id' => $this->employee->id,
            'shift_id' => ($shift ?? $this->shift)->id,
            'date' => $date,
            'status' => 'scheduled',
        ]);
    }

    // ── Stats / KPI aggregates ─────────────────────────────────────────────

    public function test_list_returns_status_breakdown_and_on_duty_counts(): void
    {
        $date = '2026-10-01';
        Attendance::create(['employee_id' => $this->employee->id, 'date' => $date, 'status' => 'present', 'worked_hours' => 8]);
        $late = Employee::create(['first_name' => 'Late', 'last_name' => 'Guy', 'hire_date' => '2026-01-01', 'status' => 'active']);
        Attendance::create(['employee_id' => $late->id, 'date' => $date, 'status' => 'late', 'worked_hours' => 8]);
        $absent = Employee::create(['first_name' => 'Absent', 'last_name' => 'Guy', 'hire_date' => '2026-01-01', 'status' => 'active']);
        Attendance::create(['employee_id' => $absent->id, 'date' => $date, 'status' => 'absent', 'worked_hours' => 0]);

        // One employee clocked in and not yet out → on duty.
        TimeClockEvent::create(['employee_id' => $this->employee->id, 'event_type' => 'in', 'event_time' => "{$date} 08:00:00", 'source' => 'web']);

        $response = $this->getJson("/api/hr/attendances?date={$date}&per_page=1");

        $response->assertOk();
        $stats = $response->json('meta.stats');
        $this->assertSame(1, $stats['present']);
        $this->assertSame(1, $stats['late']);
        $this->assertSame(1, $stats['absent']);
        $this->assertSame(0, $stats['on_leave']);
        $this->assertSame(1, $stats['on_duty']);
    }

    public function test_stats_respect_the_date_filter_window(): void
    {
        Attendance::create(['employee_id' => $this->employee->id, 'date' => '2026-10-01', 'status' => 'present', 'worked_hours' => 8]);
        Attendance::create(['employee_id' => $this->employee->id, 'date' => '2026-10-02', 'status' => 'absent', 'worked_hours' => 0]);

        $response = $this->getJson('/api/hr/attendances?date=2026-10-01&per_page=1');
        $response->assertOk();
        $this->assertSame(1, $response->json('meta.stats.present'));
        $this->assertSame(0, $response->json('meta.stats.absent'));
    }

    // ── Clock in/out self-service authorization ───────────────────────────

    public function test_employee_can_clock_in_for_self_without_manage_permission(): void
    {
        $self = User::factory()->create();
        $selfEmployee = Employee::create([
            'first_name' => 'Self',
            'last_name' => 'Service',
            'hire_date' => '2026-01-01',
            'status' => 'active',
            'user_id' => $self->id,
        ]);
        Sanctum::actingAs($self);

        $response = $this->postJson('/api/hr/time-clock/clock-in', [
            'event_time' => '2026-10-05 08:00:00',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('hr_time_clock_events', [
            'employee_id' => $selfEmployee->id,
            'event_type' => 'in',
        ]);
    }

    public function test_employee_cannot_clock_in_for_a_colleague(): void
    {
        $self = User::factory()->create();
        Employee::create([
            'first_name' => 'Self',
            'last_name' => 'Service',
            'hire_date' => '2026-01-01',
            'status' => 'active',
            'user_id' => $self->id,
        ]);
        Sanctum::actingAs($self);

        $response = $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'event_time' => '2026-10-05 08:00:00',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('hr_time_clock_events', [
            'employee_id' => $this->employee->id,
        ]);
    }

    public function test_today_status_reports_clocked_in_state_and_config(): void
    {
        $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'event_time' => now()->toDateString().' 08:00:00',
        ])->assertCreated();

        $response = $this->getJson('/api/hr/time-clock/today-status?employee_id='.$this->employee->id);

        $response->assertOk();
        $response->assertJsonPath('is_clocked_in', true);
        $response->assertJsonPath('employee.id', $this->employee->id);
        $this->assertSame(15, $response->json('config.late_checkin_grace_minutes'));
    }

    // ── Multiple daily punches ─────────────────────────────────────────────

    public function test_multiple_daily_punch_pairs_accumulate_worked_hours(): void
    {
        $day = '2026-10-06';
        $this->scheduleFor($day);

        $this->postJson('/api/hr/time-clock/clock-in', ['employee_id' => $this->employee->id, 'event_time' => "{$day} 08:00:00"])->assertCreated();
        $this->postJson('/api/hr/time-clock/clock-out', ['employee_id' => $this->employee->id, 'event_time' => "{$day} 10:00:00"])->assertCreated();
        $this->postJson('/api/hr/time-clock/clock-in', ['employee_id' => $this->employee->id, 'event_time' => "{$day} 11:00:00"])->assertCreated();
        $this->postJson('/api/hr/time-clock/clock-out', ['employee_id' => $this->employee->id, 'event_time' => "{$day} 16:00:00"])->assertCreated();

        $attendance = Attendance::where('employee_id', $this->employee->id)->whereDate('date', $day)->first();

        $this->assertNotNull($attendance);
        // 2h morning + 5h afternoon = 7h
        $this->assertSame(7.0, (float) $attendance->worked_hours);
        $this->assertSame('08:00', substr((string) $attendance->actual_in, 0, 5));
        $this->assertSame('16:00', substr((string) $attendance->actual_out, 0, 5));
    }

    public function test_late_checkin_beyond_grace_marks_late_with_exception(): void
    {
        $day = '2026-10-07';
        $this->scheduleFor($day);

        $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'event_time' => "{$day} 08:41:00", // 41 min late, grace 15
        ])->assertCreated();

        $attendance = Attendance::where('employee_id', $this->employee->id)->whereDate('date', $day)->first();
        $this->assertSame('late', $attendance->status);
        $this->assertSame(41, $attendance->late_minutes);

        $this->assertDatabaseHas('hr_attendance_exceptions', [
            'employee_id' => $this->employee->id,
            'exception_type' => 'late_arrival',
            'deviation_minutes' => 41,
        ]);
    }

    public function test_late_checkin_within_grace_stays_present(): void
    {
        $day = '2026-10-08';
        $this->scheduleFor($day);

        $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'event_time' => "{$day} 08:10:00", // 10 min late, grace 15
        ])->assertCreated();

        $attendance = Attendance::where('employee_id', $this->employee->id)->whereDate('date', $day)->first();
        $this->assertSame('present', $attendance->status);
        // Raw late minutes are informational; status only flips beyond the grace window.
        $this->assertSame(10, $attendance->late_minutes);
    }

    public function test_clocking_in_during_full_day_approved_leave_flags_mismatch_and_keeps_on_leave(): void
    {
        $day = '2026-10-09';
        $this->scheduleFor($day);

        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => $day,
            'status' => 'present',
            'worked_hours' => 0,
        ]);

        $leave = \App\Modules\Hr\Models\LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type_id' => \App\Modules\Hr\Models\LeaveType::create([
                'name' => 'Annual', 'code' => 'ANN-T', 'max_days_per_year' => 30, 'is_paid' => true,
            ])->id,
            'start_date' => $day,
            'end_date' => $day,
            'total_days' => 1,
            'status' => 'approved',
        ]);

        $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'event_time' => "{$day} 09:00:00",
        ])->assertCreated();

        $attendance = Attendance::where('employee_id', $this->employee->id)->whereDate('date', $day)->first();

        $this->assertNotNull($attendance);
        $this->assertSame('on_leave', $attendance->status);
        $this->assertSame('full_day_leave', $attendance->coverage_status);

        $this->assertDatabaseHas('hr_attendance_exceptions', [
            'employee_id' => $this->employee->id,
            'exception_type' => 'leave_mismatch',
        ]);
    }

    public function test_overnight_shift_clock_out_counts_events_across_midnight_boundary(): void
    {
        $overnight = Shift::create([
            'name' => 'Night Shift',
            'code' => 'NIGHT-22',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'is_overnight' => true,
        ]);
        $day = '2026-10-10';
        $this->scheduleFor($day, $overnight);

        $this->postJson('/api/hr/time-clock/clock-in', [
            'employee_id' => $this->employee->id,
            'event_time' => "{$day} 22:00:00",
        ])->assertCreated();

        $this->postJson('/api/hr/time-clock/clock-out', [
            'employee_id' => $this->employee->id,
            'event_time' => '2026-10-11 06:00:00',
        ])->assertCreated();

        $attendance = Attendance::where('employee_id', $this->employee->id)->whereDate('date', $day)->first();

        $this->assertNotNull($attendance);
        $this->assertSame(8.0, (float) $attendance->worked_hours);
        $this->assertGreaterThan(0, (float) $attendance->scheduled_hours);
    }

    // ── Correction workflow ────────────────────────────────────────────────

    public function test_correction_requires_valid_payload_and_reason(): void
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-10-12',
            'actual_in' => null,
            'status' => 'absent',
        ]);

        // Missing reason
        $this->postJson("/api/hr/attendances/{$attendance->id}/request-correction", [
            'requested_actual_in' => '08:00',
            'requested_actual_out' => '16:00',
        ])->assertStatus(422);

        // Invalid time format
        $this->postJson("/api/hr/attendances/{$attendance->id}/request-correction", [
            'requested_actual_in' => 'yesterday',
            'reason' => 'Lost badge',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('hr_attendance_corrections', [
            'attendance_id' => $attendance->id,
        ]);
    }

    public function test_correction_accepts_h_i_and_h_i_s_formats(): void
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-10-13',
            'actual_in' => null,
            'actual_out' => null,
            'status' => 'absent',
        ]);

        $response = $this->postJson("/api/hr/attendances/{$attendance->id}/request-correction", [
            'requested_actual_in' => '08:00',
            'requested_actual_out' => '16:30:00',
            'reason' => 'Missed punch at gate',
        ]);

        $response->assertCreated();

        $correction = AttendanceCorrection::where('attendance_id', $attendance->id)->first();
        $this->assertNotNull($correction);
        // Time columns normalize to H:i:s on MySQL; SQLite stores the typed literal.
        $this->assertSame('08:00', substr((string) $correction->requested_actual_in, 0, 5));
        $this->assertSame('16:30', substr((string) $correction->requested_actual_out, 0, 5));
    }

    public function test_rejected_correction_does_not_modify_attendance(): void
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-10-14',
            'actual_in' => '09:30:00',
            'actual_out' => '17:30:00',
            'worked_hours' => 8.0,
            'status' => 'present',
        ]);

        $correction = $this->postJson("/api/hr/attendances/{$attendance->id}/request-correction", [
            'requested_actual_in' => '08:00',
            'requested_actual_out' => '16:00',
            'reason' => 'Claims he arrived earlier',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/hr/attendance-corrections/{$correction}/decide", [
            'approved' => false,
            'decision_reason' => 'Badge log contradicts claim',
        ])->assertOk();

        $this->assertDatabaseHas('hr_attendance_corrections', [
            'id' => $correction,
            'status' => 'rejected',
        ]);

        $fresh = $attendance->fresh();
        $this->assertSame('09:30:00', $fresh->actual_in);
        $this->assertSame(8.0, (float) $fresh->worked_hours);
        $this->assertFalse((bool) $fresh->is_corrected);
    }

    public function test_approved_correction_persists_and_feeds_the_list_after_reload(): void
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-10-15',
            'actual_in' => null,
            'actual_out' => null,
            'worked_hours' => 0,
            'status' => 'absent',
        ]);

        $correctionId = $this->postJson("/api/hr/attendances/{$attendance->id}/request-correction", [
            'requested_actual_in' => '08:00',
            'requested_actual_out' => '16:00',
            'reason' => 'Supervisor forgot to punch the crew out',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/hr/attendance-corrections/{$correctionId}/decide", [
            'approved' => true,
            'decision_reason' => 'Confirmed against site log',
        ])->assertOk();

        $fresh = $attendance->fresh();
        $this->assertSame('08:00', substr((string) $fresh->actual_in, 0, 5));
        $this->assertSame('16:00', substr((string) $fresh->actual_out, 0, 5));
        $this->assertSame(8.0, (float) $fresh->worked_hours);
        $this->assertTrue((bool) $fresh->is_corrected);

        // Frontend reload path: list endpoint must return the corrected values.
        $listResponse = $this->getJson('/api/hr/attendances?date=2026-10-15&per_page=5')
            ->assertOk()
            ->assertJsonPath('data.0.is_corrected', true)
            ->assertJsonPath('data.0.corrections.0.status', 'approved');

        $this->assertSame(
            '08:00',
            substr((string) $listResponse->json('data.0.actual_in'), 0, 5),
        );
    }

    // ── Authorization ──────────────────────────────────────────────────────

    public function test_user_without_attendance_view_cannot_list_attendances(): void
    {
        $plain = User::factory()->create();
        Sanctum::actingAs($plain);

        $this->getJson('/api/hr/attendances')->assertForbidden();
    }

    public function test_manual_correction_decision_requires_manage_or_correct_permission(): void
    {
        Permission::firstOrCreate(['name' => 'hr.attendance.correct', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'hr.employees.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'hr.attendance.view', 'guard_name' => 'web']);
        $corrector = User::factory()->create();
        Role::firstOrCreate(['name' => 'attendance_corrector', 'guard_name' => 'web'])->givePermissionTo('hr.attendance.correct');
        $corrector->assignRole('attendance_corrector');

        $attendance = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-10-16',
            'actual_in' => null,
            'status' => 'absent',
        ]);

        // Request created by user, decided by independent corrector
        Sanctum::actingAs($this->user);
        $correctionId = $this->postJson("/api/hr/attendances/{$attendance->id}/request-correction", [
            'requested_actual_in' => '08:00',
            'reason' => 'Gate was closed',
        ])->assertCreated()->json('data.id');

        // corrector (hr.attendance.correct) can decide
        Sanctum::actingAs($corrector);
        $this->postJson("/api/hr/attendance-corrections/{$correctionId}/decide", ['approved' => true])
            ->assertOk();

        $this->assertDatabaseHas('hr_attendance_corrections', ['id' => $correctionId, 'status' => 'approved']);

        // A user with only view permission cannot decide the next correction
        $viewer = User::factory()->create();
        Role::firstOrCreate(['name' => 'attendance_viewer', 'guard_name' => 'web'])->givePermissionTo(['hr.employees.view', 'hr.attendance.view']);
        $viewer->assignRole('attendance_viewer');

        $second = Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-10-17',
            'actual_in' => null,
            'status' => 'absent',
        ]);

        // The Super Admin (setUp user) requests; the viewer must not approve it.
        Sanctum::actingAs($this->user);
        $secondId = $this->postJson("/api/hr/attendances/{$second->id}/request-correction", [
            'requested_actual_in' => '08:00',
            'reason' => 'Gate was closed',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($viewer);
        $this->postJson("/api/hr/attendance-corrections/{$secondId}/decide", ['approved' => true])
            ->assertForbidden();

        $this->assertDatabaseHas('hr_attendance_corrections', ['id' => $secondId, 'status' => 'pending']);
    }
}
