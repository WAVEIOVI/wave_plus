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
}
