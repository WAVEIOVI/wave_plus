<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\Attendance;
use App\Modules\Hr\Models\AttendanceConfig;
use App\Modules\Hr\Models\AttendanceCorrection;
use App\Modules\Hr\Models\AttendanceException;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\Schedule;
use App\Modules\Hr\Models\TimeClockEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttendanceCalculationService
{
    /**
     * Record a raw clock event and recalculate the daily attendance.
     */
    public function recordClockEvent(
        string $employeeId,
        string $eventType,
        Carbon $eventTime,
        string $source = 'web',
        ?string $ipAddress = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $deviceId = null,
        ?string $notes = null
    ): TimeClockEvent {
        return DB::transaction(function () use (
            $employeeId, $eventType, $eventTime, $source, $ipAddress, $latitude, $longitude, $deviceId, $notes
        ) {
            $today = $eventTime->toDateString();

            $activeSession = $this->getActiveSession($employeeId, $eventTime);

            if ($eventType === 'in') {
                if ($activeSession !== null) {
                    throw new InvalidArgumentException("Duplicate IN event detected. You must CLOCK OUT first.");
                }
            } elseif ($eventType === 'out') {
                if ($activeSession === null) {
                    throw new InvalidArgumentException("Cannot CLOCK OUT without an active CLOCK IN.");
                }
            }

            $clockEvent = TimeClockEvent::create([
                'employee_id' => $employeeId,
                'event_type' => $eventType,
                'event_time' => $eventTime,
                'source' => $source,
                'ip_address' => $ipAddress,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'device_id' => $deviceId,
                'notes' => $notes,
            ]);

            $this->recalculateAttendanceForDate($employeeId, $today);

            // Overnight shifts: a punch that closes the previous night shift must
            // also refresh the previous day's attendance record (it holds the
            // paired clock-in and the cumulative worked hours).
            if ((int) $eventTime->format('H') < Schedule::OVERNIGHT_MORNING_CUTOFF
                && $this->prevDayScheduleIsOvernight($employeeId, $today)) {
                $this->recalculateAttendanceForDate($employeeId, Carbon::parse($today)->subDay()->toDateString());
            }

            return $clockEvent;
        });
    }

    /**
     * Recalculate attendance record for a specific employee and date.
     * Incorporates schedules, shift configuration, grace periods,
     * overnight shift boundaries, approved leaves, and exception reconciliation.
     */
    public function recalculateAttendanceForDate(string $employeeId, string $dateString): Attendance
    {
        $config = AttendanceConfig::global();

        $events = TimeClockEvent::where('employee_id', $employeeId)
            ->whereDate('event_time', $dateString)
            ->orderBy('event_time')
            ->get();

        // Overnight shifts: if yesterday had an overnight shift and today has a morning out
        // that closes it, exclude that closing punch from today's daily record
        if ($this->prevDayScheduleIsOvernight($employeeId, $dateString)) {
            $yesterday = Carbon::parse($dateString)->subDay()->toDateString();
            $yesterdayOpenIn = TimeClockEvent::where('employee_id', $employeeId)
                ->whereDate('event_time', $yesterday)
                ->where('event_type', 'in')
                ->latest('event_time')
                ->first();

            if ($yesterdayOpenIn) {
                $closingOut = $events->firstWhere('event_type', 'out');
                if ($closingOut && (int) Carbon::parse($closingOut->event_time)->format('H') < Schedule::OVERNIGHT_MORNING_CUTOFF) {
                    $events = $events->reject(fn ($e) => $e->id === $closingOut->id)->values();
                }
            }
        }

        $schedule = Schedule::with('shift')
            ->where('employee_id', $employeeId)
            ->whereDate('date', $dateString)
            ->first();

        // Overnight shift: the closing clock-out lands on the next calendar day,
        // so its events must be included when pairing in/out punches.
        if ($schedule?->is_overnight) {
            $nextDay = Carbon::parse($dateString)->addDay()->toDateString();
            $nextDayEvents = TimeClockEvent::where('employee_id', $employeeId)
                ->whereDate('event_time', $nextDay)
                ->where('event_type', 'out')
                ->whereTime('event_time', '<=', sprintf('%02d:00:00', Schedule::OVERNIGHT_MORNING_CUTOFF))
                ->orderBy('event_time')
                ->get();

            $events = $events->concat($nextDayEvents)->sortBy('event_time')->values();
        }

        // Check for covering approved leave on this date
        $coveringLeave = LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $dateString)
            ->whereDate('end_date', '>=', $dateString)
            ->first();

        // Coverage status calculation
        $coverageStatus = 'normal';
        if ($coveringLeave) {
            $coverageStatus = $coveringLeave->isHourlyLeave() ? 'partial_leave' : 'full_day_leave';
        }

        $firstIn = $events->firstWhere('event_type', 'in');
        $lastOut = $events->where('event_type', 'out')->last();

        $actualInTime = $firstIn ? Carbon::parse($firstIn->event_time)->toTimeString() : null;
        $actualOutTime = $lastOut ? Carbon::parse($lastOut->event_time)->toTimeString() : null;

        $scheduledIn = $schedule?->effective_start_time ?? $schedule?->shift?->start_time;
        $scheduledOut = $schedule?->effective_end_time ?? $schedule?->shift?->end_time;
        $isOvernight = $schedule?->is_overnight ?? false;

        $scheduledHours = 0.0;
        if ($scheduledIn && $scheduledOut) {
            $schedStart = Carbon::parse("{$dateString} {$scheduledIn}");
            $schedEnd = Carbon::parse("{$dateString} {$scheduledOut}");
            if ($isOvernight || $schedEnd->lte($schedStart)) {
                $schedEnd->addDay();
            }
            $scheduledHours = round($schedStart->diffInMinutes($schedEnd) / 60, 2);
        } elseif ($schedule) {
            $scheduledHours = 8.00;
        }

        // Calculate worked hours from matched in/out pairs
        $totalWorkedMinutes = 0;
        $openIn = null;

        foreach ($events as $event) {
            if ($event->event_type === 'in') {
                $openIn = Carbon::parse($event->event_time);
            } elseif ($event->event_type === 'out' && $openIn) {
                $outTime = Carbon::parse($event->event_time);
                $totalWorkedMinutes += $openIn->diffInMinutes($outTime);
                $openIn = null;
            }
        }

        $workedHours = round($totalWorkedMinutes / 60, 2);

        // Compute late minutes & early departure
        $lateMinutes = 0;
        $earlyArrivalMinutes = 0;
        $earlyDepartureMinutes = 0;

        if ($firstIn && $scheduledIn) {
            $actualStart = Carbon::parse($firstIn->event_time);
            $scheduledStart = Carbon::parse("{$dateString} {$scheduledIn}");
            if ($actualStart->isAfter($scheduledStart)) {
                $lateMinutes = (int) $scheduledStart->diffInMinutes($actualStart);
            } elseif ($actualStart->isBefore($scheduledStart)) {
                $earlyArrivalMinutes = (int) $actualStart->diffInMinutes($scheduledStart);
            }
        }

        if ($lastOut && $scheduledOut) {
            $actualEnd = Carbon::parse($lastOut->event_time);
            $scheduledEnd = Carbon::parse("{$dateString} {$scheduledOut}");
            if ($isOvernight || $scheduledEnd->lte(Carbon::parse("{$dateString} {$scheduledIn}"))) {
                $scheduledEnd->addDay();
            }
            if ($actualEnd->isBefore($scheduledEnd)) {
                $earlyDepartureMinutes = (int) $actualEnd->diffInMinutes($scheduledEnd);
            }
        }

        $overtimeHours = max(0, round($workedHours - $scheduledHours, 2));

        // Determine attendance status
        $status = 'present';
        if ($coverageStatus === 'full_day_leave') {
            $status = 'on_leave';
        } elseif ($events->isEmpty()) {
            if ($schedule && Carbon::parse($dateString)->isPast()) {
                $status = 'absent';
                $coverageStatus = 'absence';
            } else {
                $status = 'absent';
            }
        } else {
            $lateGrace = $config->late_checkin_grace_minutes;
            if ($lateMinutes > $lateGrace) {
                $status = 'late';
            } elseif ($scheduledHours > 0 && $workedHours < ($scheduledHours / 2) && $lastOut) {
                $status = 'partial';
            }
        }

        $attributes = [
            'scheduled_in' => $scheduledIn,
            'scheduled_out' => $scheduledOut,
            'actual_in' => $actualInTime,
            'actual_out' => $actualOutTime,
            'scheduled_hours' => $scheduledHours,
            'worked_hours' => $workedHours,
            'late_minutes' => $lateMinutes,
            'early_departure_minutes' => $earlyDepartureMinutes,
            'overtime_hours' => $overtimeHours,
            'status' => $status,
            'coverage_status' => $coverageStatus,
            'schedule_id' => $schedule?->id,
            'covering_leave_id' => $coveringLeave?->id,
        ];

        $attendance = Attendance::where('employee_id', $employeeId)
            ->whereDate('date', $dateString)
            ->first();

        if ($attendance) {
            $attendance->update($attributes);
        } else {
            $attendance = Attendance::create(array_merge([
                'employee_id' => $employeeId,
                'date' => $dateString,
            ], $attributes));
        }

        // Update schedule coverage status if schedule exists
        if ($schedule && $schedule->coverage_status !== $coverageStatus) {
            $schedule->update(['coverage_status' => $coverageStatus]);
        }

        // Reconcile and record attendance exceptions
        $this->syncExceptions(
            attendance: $attendance,
            config: $config,
            firstIn: $firstIn,
            lastOut: $lastOut,
            lateMinutes: $lateMinutes,
            earlyArrivalMinutes: $earlyArrivalMinutes,
            earlyDepartureMinutes: $earlyDepartureMinutes,
            scheduledIn: $scheduledIn,
            scheduledOut: $scheduledOut,
            coveringLeave: $coveringLeave
        );

        return $attendance;
    }

    /**
     * Reconcile reality against plan and create/sync AttendanceException records.
     */
    protected function syncExceptions(
        Attendance $attendance,
        AttendanceConfig $config,
        ?TimeClockEvent $firstIn,
        ?TimeClockEvent $lastOut,
        int $lateMinutes,
        int $earlyArrivalMinutes,
        int $earlyDepartureMinutes,
        ?string $scheduledIn,
        ?string $scheduledOut,
        ?LeaveRequest $coveringLeave
    ): void {
        $dateStr = $attendance->date instanceof Carbon ? $attendance->date->toDateString() : (string) $attendance->date;

        $saveException = function (string $type, array $extraAttrs) use ($attendance, $dateStr) {
            $existing = AttendanceException::where('employee_id', $attendance->employee_id)
                ->whereDate('date', $dateStr)
                ->where('exception_type', $type)
                ->first();

            if ($existing) {
                $existing->update($extraAttrs);
            } else {
                AttendanceException::create(array_merge([
                    'employee_id' => $attendance->employee_id,
                    'date' => $dateStr,
                    'exception_type' => $type,
                ], $extraAttrs));
            }
        };

        // 1. Late arrival exception
        if ($lateMinutes > $config->late_checkin_grace_minutes) {
            $saveException('late_arrival', [
                'attendance_id' => $attendance->id,
                'time_clock_event_id' => $firstIn?->id,
                'expected_time' => $scheduledIn,
                'actual_time' => $attendance->actual_in,
                'deviation_minutes' => $lateMinutes,
                'covering_leave_request_id' => $coveringLeave?->id,
                'review_status' => AttendanceException::REVIEW_OPEN,
            ]);
        }

        // 2. Early arrival exception (if exceeded grace)
        if ($earlyArrivalMinutes > $config->early_checkin_grace_minutes) {
            $saveException('early_arrival', [
                'attendance_id' => $attendance->id,
                'time_clock_event_id' => $firstIn?->id,
                'expected_time' => $scheduledIn,
                'actual_time' => $attendance->actual_in,
                'deviation_minutes' => $earlyArrivalMinutes,
                'covering_leave_request_id' => $coveringLeave?->id,
                'review_status' => AttendanceException::REVIEW_OPEN,
            ]);
        }

        // 3. Early departure exception
        if ($earlyDepartureMinutes > $config->early_checkout_grace_minutes) {
            $saveException('early_departure', [
                'attendance_id' => $attendance->id,
                'time_clock_event_id' => $lastOut?->id,
                'expected_time' => $scheduledOut,
                'actual_time' => $attendance->actual_out,
                'deviation_minutes' => $earlyDepartureMinutes,
                'covering_leave_request_id' => $coveringLeave?->id,
                'review_status' => AttendanceException::REVIEW_OPEN,
            ]);
        }

        // 4. Overtime exception (more than 30 minutes over scheduled hours)
        if ($attendance->overtime_hours > 0.5) {
            $saveException('overtime', [
                'attendance_id' => $attendance->id,
                'time_clock_event_id' => $lastOut?->id,
                'expected_time' => $scheduledOut,
                'actual_time' => $attendance->actual_out,
                'deviation_minutes' => (int) round($attendance->overtime_hours * 60),
                'covering_leave_request_id' => $coveringLeave?->id,
                'review_status' => AttendanceException::REVIEW_OPEN,
            ]);
        }

        // 5. Leave mismatch: employee clocked in while on approved full day leave
        if ($coveringLeave && ! $coveringLeave->isHourlyLeave() && $attendance->actual_in) {
            $saveException('leave_mismatch', [
                'attendance_id' => $attendance->id,
                'time_clock_event_id' => $firstIn?->id,
                'expected_time' => 'On Leave',
                'actual_time' => $attendance->actual_in,
                'deviation_minutes' => 0,
                'covering_leave_request_id' => $coveringLeave->id,
                'notes' => 'Employee clocked in during approved full-day leave ('.$coveringLeave->leaveType?->name.')',
                'review_status' => AttendanceException::REVIEW_OPEN,
            ]);
        }
    }

    /**
     * Apply an approved attendance correction.
     */
    public function applyCorrection(AttendanceCorrection $correction, string $approverId, bool $approved, ?string $decisionReason = null): void
    {
        DB::transaction(function () use ($correction, $approverId, $approved, $decisionReason) {
            $correction->update([
                'approver_id' => $approverId,
                'status' => $approved ? 'approved' : 'rejected',
                'decision_reason' => $decisionReason,
                'decided_at' => now(),
            ]);

            if ($approved) {
                $attendance = $correction->attendance;
                $attendance->update([
                    'actual_in' => $correction->requested_actual_in ?? $attendance->actual_in,
                    'actual_out' => $correction->requested_actual_out ?? $attendance->actual_out,
                    'is_corrected' => true,
                ]);

                // Recalculate hours
                if ($attendance->actual_in && $attendance->actual_out) {
                    $dateStr = $attendance->date instanceof Carbon ? $attendance->date->toDateString() : (string) $attendance->date;
                    $start = Carbon::parse("{$dateStr} {$attendance->actual_in}");
                    $end = Carbon::parse("{$dateStr} {$attendance->actual_out}");
                    $workedHours = round($start->diffInMinutes($end) / 60, 2);
                    $attendance->update([
                        'worked_hours' => $workedHours,
                        'overtime_hours' => max(0, round($workedHours - $attendance->scheduled_hours, 2)),
                        'status' => 'present',
                    ]);
                }
            }
        });
    }

    public function prevDayScheduleIsOvernight(string $employeeId, string $today): bool
    {
        $yesterday = Carbon::parse($today)->subDay()->toDateString();

        return Schedule::with('shift')
            ->where('employee_id', $employeeId)
            ->whereDate('date', $yesterday)
            ->get()
            ->contains(fn ($s) => $s->is_overnight);
    }

    private function getOppositeEvent(string $type): string
    {
        return $type === 'in' ? 'CLOCK OUT' : 'CLOCK IN';
    }

    /**
     * Locate the currently active clock-in event for an employee, handling
     * overnight sessions that crossed the midnight boundary.
     */
    public function getActiveSession(string $employeeId, ?Carbon $asOf = null): ?TimeClockEvent
    {
        $now = $asOf ? $asOf->copy() : Carbon::now();
        $today = $now->toDateString();

        // 1. Check events on the target date
        $todayQuery = TimeClockEvent::where('employee_id', $employeeId)
            ->whereDate('event_time', $today);

        if ($asOf !== null) {
            $todayQuery->where('event_time', '<=', $now);
        }

        $lastEventToday = $todayQuery->orderByDesc('event_time')->first();

        if ($lastEventToday) {
            return $lastEventToday->event_type === 'in' ? $lastEventToday : null;
        }

        // 2. No events today yet: check if there's an open clock-in from yesterday's overnight shift
        $yesterday = Carbon::parse($today)->subDay()->toDateString();
        $isOvernightShift = $this->prevDayScheduleIsOvernight($employeeId, $today);
        $isWithinMorningCutoff = (int) $now->format('H') < Schedule::OVERNIGHT_MORNING_CUTOFF;

        if ($isOvernightShift || $isWithinMorningCutoff) {
            $lastEventYesterday = TimeClockEvent::where('employee_id', $employeeId)
                ->whereDate('event_time', $yesterday)
                ->orderByDesc('event_time')
                ->first();

            if ($lastEventYesterday && $lastEventYesterday->event_type === 'in') {
                return $lastEventYesterday;
            }
        }

        return null;
    }

    /**
     * Resolve the next expected punch type ('in' or 'out') for an employee.
     */
    public function resolveNextEventType(string $employeeId, ?Carbon $asOf = null): string
    {
        return $this->getActiveSession($employeeId, $asOf) !== null ? 'out' : 'in';
    }

    /**
     * Last time-clock event relevant for duplicate/toggle decisions.
     * Extends into the previous calendar day when an overnight shift is
     * involved, so a night crew's morning clock-out closes the previous
     * shift's clock-in instead of being treated as a fresh 'in'.
     */
    public function lastEventForEmployee(string $employeeId, Carbon $eventTime): ?TimeClockEvent
    {
        $activeSession = $this->getActiveSession($employeeId, $eventTime);
        if ($activeSession) {
            return $activeSession;
        }

        $today = $eventTime->toDateString();

        $lastEvent = TimeClockEvent::where('employee_id', $employeeId)
            ->whereDate('event_time', $today)
            ->where('event_time', '<=', $eventTime)
            ->orderByDesc('event_time')
            ->first();

        if ($lastEvent) {
            return $lastEvent;
        }

        $yesterday = Carbon::parse($today)->subDay()->toDateString();

        $overnightContext = Schedule::where('employee_id', $employeeId)
            ->whereIn('date', [$today, $yesterday])
            ->get()
            ->contains(fn ($s) => $s->is_overnight);

        if (! $overnightContext) {
            return null;
        }

        return TimeClockEvent::where('employee_id', $employeeId)
            ->whereDate('event_time', $yesterday)
            ->orderByDesc('event_time')
            ->first();
    }
}
