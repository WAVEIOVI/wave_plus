<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Concerns\AuthorizesHrMutations;
use App\Modules\Hr\Models\Attendance;
use App\Modules\Hr\Models\AttendanceConfig;
use App\Modules\Hr\Models\AttendanceException;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\HrAuditLog;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\Schedule;
use App\Modules\Hr\Models\TimeClockEvent;
use App\Modules\Hr\Requests\ClockEventRequest;
use App\Modules\Hr\Services\AttendanceCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeClockController extends Controller
{
    use AuthorizesHrMutations;

    /**
     * Namespaced prefix embedded in every printable employee badge QR code.
     * Scanning a bare employee number also works as a fallback.
     */
    public const BADGE_PREFIX = 'WP:EMP:';

    public function __construct(
        protected AttendanceCalculationService $attendanceService
    ) {}

    /**
     * Resolve the printable badge profile (and QR payload) for an employee.
     * The QR image itself is rendered client-side from `qr_payload`.
     */
    public function badge(Request $request, string $employeeId): JsonResponse
    {
        $viewer = $request->user();
        $employee = Employee::with(['department', 'position', 'currentTeam'])->findOrFail($employeeId);

        $canViewOthers = $viewer->hasRole(['Super Admin', 'Admin'])
            || $viewer->can('hr.attendance.view')
            || $viewer->can('hr.attendance.manage')
            || $viewer->can('read-PeopleSkills');

        if (! $canViewOthers) {
            $linked = Employee::where('user_id', $viewer->id)->first();
            abort_unless($linked && $linked->id === $employee->id, 403, 'You can only view your own badge.');
        }

        return response()->json(['data' => $this->badgePayload($employee)]);
    }

    /**
     * Scan a badge (QR payload, employee number or numeric PIN) and record the
     * next logical punch for that employee (auto in/out toggle unless explicit).
     */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'badge' => ['required', 'string', 'max:191'],
            'event_type' => ['nullable', 'in:in,out'],
            'event_time' => ['nullable', 'date'],
            'source' => ['nullable', 'in:web,mobile,manual,qr,pin,device,biometric'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = $this->resolveBadgeEmployee($data['badge']);

        if (! $employee) {
            return response()->json([
                'message' => 'Badge not recognised. Check the QR code or use the PIN keypad fallback.',
            ], 404);
        }

        if ($employee->status !== 'active') {
            return response()->json([
                'message' => "Employee {$employee->full_name} is {$employee->status} and cannot record attendance.",
            ], 422);
        }

        $user = $request->user();

        // Kiosk authorization: never trust client-supplied device_id or kiosk flag as proof.
        // Requires server-validated credentials with scoped kiosk or management permissions.
        $isAuthorizedKioskOrManager = $user->hasRole(['Super Admin', 'Admin', 'Attendance Kiosk', 'kiosk'])
            || $user->can('hr.attendance.manage')
            || $user->can('hr.attendance.kiosk');

        $linked = Employee::where('user_id', $user->id)->first();

        if (! $isAuthorizedKioskOrManager) {
            abort_unless($linked && $linked->id === $employee->id, 403, 'You can only clock in or out with your own badge.');
        }

        // Server-generated timestamp for normal punches; only authorized managers may supply historical time
        $eventTime = ($isAuthorizedKioskOrManager && ! empty($data['event_time']))
            ? Carbon::parse($data['event_time'])
            : now();

        $today = $eventTime->toDateString();

        // Use shared overnight-aware event resolution service
        $eventType = $data['event_type']
            ?? $this->attendanceService->resolveNextEventType($employee->id, $eventTime);

        try {
            $event = $this->attendanceService->recordClockEvent(
                employeeId: $employee->id,
                eventType: $eventType,
                eventTime: $eventTime,
                source: $data['source'] ?? 'qr',
                ipAddress: $request->ip(),
                latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
                longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
                deviceId: $data['device_id'] ?? null,
                notes: $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Audit log for kiosk/device operations clocking employees
        if ($isAuthorizedKioskOrManager && (! $linked || $linked->id !== $employee->id)) {
            HrAuditLog::create([
                'auditable_type' => Employee::class,
                'auditable_id' => $employee->id,
                'actor_id' => $user->id,
                'action' => 'kiosk_punch',
                'new_values' => [
                    'event_type' => $eventType,
                    'event_time' => $eventTime->toIso8601String(),
                    'source' => $data['source'] ?? 'qr',
                    'device_id' => $data['device_id'] ?? null,
                ],
                'reason' => 'Attendance kiosk punch recorded',
                'ip_address' => $request->ip(),
            ]);
        }

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        return response()->json([
            'message' => $eventType === 'in'
                ? "{$employee->full_name} clocked IN at {$eventTime->format('H:i')}."
                : "{$employee->full_name} clocked OUT at {$eventTime->format('H:i')}.",
            'action' => $eventType,
            'employee' => $this->badgePayload($employee),
            'event' => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'time' => $event->event_time->format('H:i:s'),
                'datetime' => $event->event_time->toISOString(),
                'source' => $event->source,
            ],
            'attendance' => $attendance ? [
                'id' => $attendance->id,
                'actual_in' => $attendance->actual_in,
                'actual_out' => $attendance->actual_out,
                'worked_hours' => $attendance->worked_hours,
                'late_minutes' => $attendance->late_minutes,
                'status' => $attendance->status,
            ] : null,
        ], 201);
    }

    /**
     * Punch a whole crew in one action (shift start/end, site muster, etc.).
     */
    public function bulk(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.attendance.manage');

        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1', 'max:200'],
            'employee_ids.*' => ['ulid', 'required', 'exists:hr_employees,id'],
            'event_type' => ['required', 'in:in,out'],
            'event_time' => ['nullable', 'date'],
            'source' => ['nullable', 'in:web,mobile,manual,qr,device,biometric'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $eventTime = ! empty($data['event_time']) ? Carbon::parse($data['event_time']) : now();

        $results = [];
        $recorded = 0;
        $failed = 0;

        foreach (array_unique($data['employee_ids']) as $employeeId) {
            $employee = Employee::find($employeeId);

            if (! $employee || $employee->status !== 'active') {
                $failed++;
                $results[] = [
                    'employee_id' => $employeeId,
                    'full_name' => $employee?->full_name,
                    'status' => 'skipped',
                    'message' => $employee ? "Employee is {$employee->status}." : 'Employee not found.',
                ];
                continue;
            }

            try {
                $this->attendanceService->recordClockEvent(
                    employeeId: $employeeId,
                    eventType: $data['event_type'],
                    eventTime: $eventTime,
                    source: $data['source'] ?? 'manual',
                    ipAddress: $request->ip(),
                    notes: $data['notes'] ?? null,
                );
                $recorded++;
                $results[] = [
                    'employee_id' => $employeeId,
                    'full_name' => $employee?->full_name,
                    'status' => 'recorded',
                ];
            } catch (\Throwable $e) {
                $failed++;
                $results[] = [
                    'employee_id' => $employeeId,
                    'full_name' => $employee?->full_name,
                    'status' => 'skipped',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => trim("{$recorded} punch(es) recorded".($failed ? ", {$failed} skipped." : '.')),
            'recorded' => $recorded,
            'failed' => $failed,
            'results' => $results,
        ], 201);
    }

    public function todayStatus(Request $request): JsonResponse
    {
        $employee = $this->resolveClockEmployeeForStatus($request);

        if (! $employee) {
            return response()->json([
                'is_linked' => false,
                'message' => 'No linked employee record found for current user.',
            ]);
        }

        $today = Carbon::today()->toDateString();
        $employeeId = $employee->id;

        $events = TimeClockEvent::where('employee_id', $employeeId)
            ->whereDate('event_time', $today)
            ->orderBy('event_time')
            ->get();

        $activeSession = $this->attendanceService->getActiveSession($employeeId);
        $isClockedIn = $activeSession !== null;

        $lastEvent = $events->last();
        if (! $lastEvent && $activeSession) {
            $lastEvent = $activeSession;
        }

        $attendance = Attendance::where('employee_id', $employeeId)
            ->where('date', $today)
            ->first();

        // If today has no attendance record yet but an overnight shift was clocked yesterday
        if (! $attendance && $activeSession) {
            $yesterday = Carbon::yesterday()->toDateString();
            $attendance = Attendance::where('employee_id', $employeeId)
                ->where('date', $yesterday)
                ->first();
        }

        // Get schedule for today
        $schedule = Schedule::with('shift')
            ->where('employee_id', $employeeId)
            ->where('date', $today)
            ->first();

        if (! $schedule && $activeSession) {
            $yesterday = Carbon::yesterday()->toDateString();
            $schedule = Schedule::with('shift')
                ->where('employee_id', $employeeId)
                ->where('date', $yesterday)
                ->first();
        }

        // Get covering approved leave for today
        $coveringLeave = LeaveRequest::with('leaveType')
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->first();

        // Active exceptions today
        $exceptions = AttendanceException::where('employee_id', $employeeId)
            ->where('date', $today)
            ->get();

        $config = AttendanceConfig::global();

        return response()->json([
            'is_linked' => true,
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'department' => $employee->department?->name,
                'team' => $employee->currentTeam?->name,
                'avatar_url' => $employee->avatar_url,
            ],
            'is_clocked_in' => $isClockedIn,
            'last_event' => $lastEvent ? [
                'id' => $lastEvent->id,
                'type' => $lastEvent->event_type,
                'time' => $lastEvent->event_time->format('H:i:s'),
                'datetime' => $lastEvent->event_time->toISOString(),
            ] : null,
            'today_events' => $events->map(fn ($e) => [
                'id' => $e->id,
                'type' => $e->event_type,
                'time' => $e->event_time->format('H:i'),
                'datetime' => $e->event_time->toISOString(),
                'source' => $e->source,
                'notes' => $e->notes,
            ]),
            'attendance' => $attendance ? [
                'id' => $attendance->id,
                'actual_in' => $attendance->actual_in,
                'actual_out' => $attendance->actual_out,
                'worked_hours' => $attendance->worked_hours,
                'scheduled_hours' => $attendance->scheduled_hours,
                'late_minutes' => $attendance->late_minutes,
                'early_departure_minutes' => $attendance->early_departure_minutes,
                'status' => $attendance->status,
                'coverage_status' => $attendance->coverage_status,
            ] : null,
            'schedule' => $schedule ? [
                'id' => $schedule->id,
                'shift_name' => $schedule->shift?->name,
                'shift_code' => $schedule->shift?->code,
                'start_time' => $schedule->effective_start_time,
                'end_time' => $schedule->effective_end_time,
                'is_overnight' => $schedule->is_overnight,
                'coverage_status' => $schedule->coverage_status,
            ] : null,
            'covering_leave' => $coveringLeave ? [
                'id' => $coveringLeave->id,
                'leave_type_name' => $coveringLeave->leaveType?->name,
                'duration_type' => $coveringLeave->duration_type,
                'start_time' => $coveringLeave->start_time,
                'end_time' => $coveringLeave->end_time,
            ] : null,
            'exceptions' => $exceptions->map(fn ($ex) => [
                'id' => $ex->id,
                'type' => $ex->exception_type,
                'deviation_minutes' => $ex->deviation_minutes,
                'review_status' => $ex->review_status,
            ]),
            'config' => [
                'early_checkin_grace_minutes' => $config->early_checkin_grace_minutes,
                'late_checkin_grace_minutes' => $config->late_checkin_grace_minutes,
                'early_checkout_grace_minutes' => $config->early_checkout_grace_minutes,
                'late_checkout_grace_minutes' => $config->late_checkout_grace_minutes,
            ],
        ]);
    }

    /**
     * Preview deviation context before submitting clock event.
     */
    public function previewContext(Request $request): JsonResponse
    {
        $employee = $this->resolveClockEmployee($request);
        $eventType = $request->input('event_type', 'in');
        $now = $request->input('event_time') ? Carbon::parse($request->input('event_time')) : Carbon::now();
        $today = $now->toDateString();

        $schedule = Schedule::with('shift')
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->first();

        // If checking out and no schedule found for today, check yesterday for an overnight schedule
        if (! $schedule && $eventType === 'out') {
            $yesterday = Carbon::yesterday()->toDateString();
            $yesterdaySchedule = Schedule::with('shift')
                ->where('employee_id', $employee->id)
                ->where('date', $yesterday)
                ->where('is_overnight', true)
                ->first();
            if ($yesterdaySchedule) {
                $schedule = $yesterdaySchedule;
                $today = $yesterday;
            }
        }

        $config = AttendanceConfig::global();
        $status = 'normal';
        $deviationMinutes = 0;
        $requiresReason = false;
        $message = null;

        if ($schedule) {
            $scheduledIn = $schedule->effective_start_time;
            $scheduledOut = $schedule->effective_end_time;

            if ($eventType === 'in' && $scheduledIn) {
                $schedStart = Carbon::parse("{$today} {$scheduledIn}");
                if ($now->isAfter($schedStart)) {
                    $deviationMinutes = (int) $schedStart->diffInMinutes($now);
                    if ($deviationMinutes > $config->late_checkin_grace_minutes) {
                        $status = 'late';
                        $requiresReason = true;
                        $message = "Late arrival by {$deviationMinutes} min (Grace: {$config->late_checkin_grace_minutes} min). Please provide a note.";
                    }
                } elseif ($now->isBefore($schedStart)) {
                    $deviationMinutes = (int) $now->diffInMinutes($schedStart);
                    if ($deviationMinutes > $config->early_checkin_grace_minutes) {
                        $status = 'early';
                        $message = "Early arrival by {$deviationMinutes} min.";
                    }
                }
            } elseif ($eventType === 'out' && $scheduledOut) {
                $schedEnd = Carbon::parse("{$today} {$scheduledOut}");
                if ($schedule->is_overnight && $schedEnd->lte(Carbon::parse("{$today} {$scheduledIn}"))) {
                    $schedEnd->addDay();
                }
                if ($now->isBefore($schedEnd)) {
                    $deviationMinutes = (int) $now->diffInMinutes($schedEnd);
                    if ($deviationMinutes > $config->early_checkout_grace_minutes) {
                        $status = 'early_departure';
                        $requiresReason = true;
                        $message = "Leaving early by {$deviationMinutes} min before scheduled end ({$scheduledOut}). Please provide a reason.";
                    }
                }
            }
        }

        return response()->json([
            'status' => $status,
            'deviation_minutes' => $deviationMinutes,
            'requires_reason' => $requiresReason,
            'message' => $message,
            'scheduled_time' => $eventType === 'in' ? ($schedule?->effective_start_time) : ($schedule?->effective_end_time),
        ]);
    }

    public function clockIn(ClockEventRequest $request): JsonResponse
    {
        return $this->storeClockEvent($request, 'in', 'Clock In recorded successfully.');
    }

    public function clockOut(ClockEventRequest $request): JsonResponse
    {
        return $this->storeClockEvent($request, 'out', 'Clock Out recorded successfully.');
    }

    /**
     * Build the shared badge profile payload used by badge + scan responses.
     */
    protected function badgePayload(Employee $employee): array
    {
        return [
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
            'position' => $employee->position?->title,
            'department' => $employee->department?->name,
            'team' => $employee->currentTeam?->name,
            'status' => $employee->status,
            'avatar_url' => $employee->avatar_url,
            'qr_payload' => self::BADGE_PREFIX.$employee->employee_number,
        ];
    }

    /**
     * Resolve an employee from a scanned badge string.
     * Accepts: `WP:EMP:EMP-00001`, a bare employee number, a badge URL, or a
     * numeric PIN (left zero-padded) as a lost-badge fallback.
     */
    protected function resolveBadgeEmployee(string $raw): ?Employee
    {
        $code = trim($raw);

        if (str_starts_with($code, self::BADGE_PREFIX)) {
            $code = substr($code, strlen(self::BADGE_PREFIX));
        } elseif (preg_match('~^(?:WAVEPLUS|WP)[:/_-]*EMP[:/_-]?(.+)$~i', $code, $matches)) {
            $code = $matches[1];
        }

        if (preg_match('~^https?://~i', $code)) {
            $code = basename((string) parse_url($code, PHP_URL_PATH));
        }

        $code = trim($code);

        if ($code === '') {
            return null;
        }

        $relations = ['department', 'position', 'currentTeam'];

        $employee = Employee::with($relations)
            ->whereRaw('LOWER(employee_number) = ?', [mb_strtolower($code)])
            ->first();

        if ($employee) {
            return $employee;
        }

        // PIN fallback: the numeric sequence of the employee number, zero padded.
        if (preg_match('~^\d{1,6}$~', $code)) {
            $padded = str_pad($code, 5, '0', STR_PAD_LEFT);

            $matches = Employee::with($relations)
                ->where(function ($query) use ($code, $padded) {
                    $query->where('employee_number', 'like', '%-'.$code)
                        ->orWhere('employee_number', 'like', '%-'.$padded);
                })
                ->limit(2)
                ->get();

            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
    }

    private function storeClockEvent(ClockEventRequest $request, string $eventType, string $message): JsonResponse
    {
        $employee = $this->resolveClockEmployee($request);

        if ($employee->status !== 'active') {
            return response()->json([
                'message' => "Employee {$employee->full_name} is {$employee->status} and cannot record attendance.",
            ], 422);
        }

        $user = $request->user();
        $isAuthorizedManager = $user->hasRole(['Super Admin', 'Admin']) || $user->can('hr.attendance.manage');

        // Server-generated timestamp for normal punches; only authorized managers may supply historical time
        $eventTime = ($isAuthorizedManager && $request->input('event_time'))
            ? Carbon::parse($request->input('event_time'))
            : now();

        try {
            $event = $this->attendanceService->recordClockEvent(
                employeeId: $employee->id,
                eventType: $eventType,
                eventTime: $eventTime,
                source: $request->input('source', 'web'),
                ipAddress: $request->ip(),
                latitude: $request->input('latitude') ? (float) $request->input('latitude') : null,
                longitude: $request->input('longitude') ? (float) $request->input('longitude') : null,
                deviceId: $request->input('device_id'),
                notes: $request->input('notes'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($isAuthorizedManager && $request->input('event_time')) {
            HrAuditLog::create([
                'auditable_type' => Employee::class,
                'auditable_id' => $employee->id,
                'actor_id' => $user->id,
                'action' => 'historical_punch_adjusted',
                'new_values' => [
                    'event_type' => $eventType,
                    'event_time' => $eventTime->toIso8601String(),
                    'source' => $request->input('source', 'web'),
                ],
                'reason' => $request->input('notes') ?? 'Historical punch adjustment by authorized manager',
                'ip_address' => $request->ip(),
            ]);
        }

        return response()->json([
            'message' => $message,
            'event' => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'type' => $event->event_type,
                'time' => $event->event_time->format('H:i:s'),
                'datetime' => $event->event_time->toISOString(),
            ],
        ], 201);
    }
}
