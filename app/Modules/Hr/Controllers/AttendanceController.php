<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\Attendance;
use App\Modules\Hr\Models\AttendanceConfig;
use App\Modules\Hr\Models\AttendanceCorrection;
use App\Modules\Hr\Models\TimeClockEvent;
use App\Modules\Hr\Requests\AttendanceCorrectionRequest;
use App\Modules\Hr\Resources\AttendanceResource;
use App\Modules\Hr\Services\AttendanceCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AttendanceCalculationService $attendanceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $date = $request->input('date');
        $startDate = $request->input('start_date');
        $endDate = null;

        $query = Attendance::with([
            'employee.department',
            'corrections.requester',
            'corrections.approver',
            'coveringLeave.leaveType',
            'exceptions',
        ]);

        if ($date) {
            $query->whereDate('date', $date);
        } elseif ($startDate) {
            $endDate = $request->input('end_date', $startDate);
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        if ($empId = $request->input('employee_id')) {
            $query->where('employee_id', $empId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($deptId = $request->input('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        }

        $attendances = $query->orderByDesc('date')->paginate((int) $request->input('per_page', 20));

        if ($attendances->isNotEmpty()) {
            $minDate = $attendances->min('date')->toDateString();
            $maxDate = $attendances->max('date')->toDateString();
            $empIds = $attendances->pluck('employee_id')->unique()->all();

            $events = TimeClockEvent::whereIn('employee_id', $empIds)
                ->whereDate('event_time', '>=', $minDate)
                ->whereDate('event_time', '<=', $maxDate)
                ->orderBy('event_time')
                ->get()
                ->groupBy(fn ($e) => $e->employee_id . '_' . $e->event_time->toDateString());

            foreach ($attendances as $attendance) {
                $key = $attendance->employee_id . '_' . $attendance->date->toDateString();
                $attendance->setRelation('clockEvents', $events->get($key, collect()));
            }
        }

        // KPI aggregates over the same filter window (without the status filter
        // so the breakdown reflects all states for that date/range).
        $statsQuery = Attendance::query()
            ->when($date, fn ($q) => $q->whereDate('date', $date))
            ->when($startDate && ! $date, fn ($q) => $q->whereBetween('date', [$startDate, $request->input('end_date', $startDate)]))
            ->when($empId, fn ($q) => $q->where('employee_id', $empId))
            ->when($deptId, fn ($q) => $q->whereHas('employee', fn ($sq) => $sq->where('department_id', $deptId)));

        $statusCounts = $statsQuery->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $onDuty = 0;
        if ($date) {
            // Employees whose last event today is a clock-in are currently on duty.
            $onDuty = TimeClockEvent::query()
                ->selectRaw('employee_id, MAX(event_time) as last_time')
                ->whereDate('event_time', $date)
                ->groupBy('employee_id')
                ->get()
                ->map(fn ($row) => TimeClockEvent::where('employee_id', $row->employee_id)
                    ->where('event_time', $row->last_time)
                    ->value('event_type'))
                ->filter(fn ($type) => $type === 'in')
                ->count();
        }

        return response()->json([
            'data' => AttendanceResource::collection($attendances->getCollection())->resolve(),
            'meta' => [
                'current_page' => $attendances->currentPage(),
                'per_page' => $attendances->perPage(),
                'total' => $attendances->total(),
                'last_page' => $attendances->lastPage(),
                'stats' => [
                    'present' => (int) ($statusCounts['present'] ?? 0),
                    'late' => (int) ($statusCounts['late'] ?? 0),
                    'absent' => (int) ($statusCounts['absent'] ?? 0),
                    'on_leave' => (int) ($statusCounts['on_leave'] ?? 0),
                    'partial' => (int) ($statusCounts['partial'] ?? 0),
                    'on_duty' => $onDuty,
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $attendance = Attendance::with(['employee.department', 'corrections.requester', 'corrections.approver'])->findOrFail($id);
        $this->authorize('view', $attendance);

        $events = TimeClockEvent::where('employee_id', $attendance->employee_id)
            ->whereDate('event_time', $attendance->date->toDateString())
            ->orderBy('event_time')
            ->get();
        $attendance->setRelation('clockEvents', $events);

        return response()->json([
            'data' => new AttendanceResource($attendance),
        ]);
    }

    public function requestCorrection(AttendanceCorrectionRequest $request, string $id): JsonResponse
    {
        $attendance = Attendance::findOrFail($id);
        $this->authorize('correct', $attendance);

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'requester_id' => auth()->id(),
            'old_actual_in' => $attendance->actual_in,
            'old_actual_out' => $attendance->actual_out,
            'requested_actual_in' => $request->input('requested_actual_in'),
            'requested_actual_out' => $request->input('requested_actual_out'),
            'reason' => $request->input('reason'),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Attendance correction requested.',
            'data' => $correction,
        ], 201);
    }

    public function decideCorrection(Request $request, string $correctionId): JsonResponse
    {
        $correction = AttendanceCorrection::with('attendance')->findOrFail($correctionId);
        $this->authorize('correct', $correction->attendance);

        $request->validate([
            'approved' => ['required', 'boolean'],
            'decision_reason' => ['nullable', 'string'],
        ]);

        $this->attendanceService->applyCorrection(
            $correction,
            auth()->id(),
            $request->boolean('approved'),
            $request->input('decision_reason')
        );

        return response()->json([
            'message' => $request->boolean('approved') ? 'Correction approved and applied.' : 'Correction rejected.',
            'status' => $correction->fresh()->status,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage', Attendance::class);

        $data = $request->validate([
            'employee_id' => ['ulid', 'required', 'exists:hr_employees,id'],
            'date' => ['required', 'date'],
            'scheduled_in' => ['nullable', 'string'],
            'scheduled_out' => ['nullable', 'string'],
            'actual_in' => ['nullable', 'string'],
            'actual_out' => ['nullable', 'string'],
            'status' => ['nullable', 'in:present,late,absent,on_leave,half_day,partial'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($data['actual_in']) && ! empty($data['actual_out'])) {
            $in = Carbon::parse($data['actual_in']);
            $out = Carbon::parse($data['actual_out']);
            $data['worked_hours'] = max(0, round($out->diffInMinutes($in) / 60, 2));
        }

        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'date' => $data['date']],
            $data
        );

        return response()->json([
            'message' => 'Attendance logged successfully.',
            'data' => new AttendanceResource($attendance->load(['employee.department', 'corrections'])),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize('manage', Attendance::class);
        $attendance = Attendance::findOrFail($id);

        $data = $request->validate([
            'date' => ['sometimes', 'required', 'date'],
            'scheduled_in' => ['nullable', 'string'],
            'scheduled_out' => ['nullable', 'string'],
            'actual_in' => ['nullable', 'string'],
            'actual_out' => ['nullable', 'string'],
            'status' => ['nullable', 'in:present,late,absent,on_leave,half_day,partial'],
            'notes' => ['nullable', 'string'],
        ]);

        if (array_key_exists('actual_in', $data) || array_key_exists('actual_out', $data)) {
            $inTime = $data['actual_in'] ?? $attendance->actual_in;
            $outTime = $data['actual_out'] ?? $attendance->actual_out;
            if ($inTime && $outTime) {
                $in = Carbon::parse($inTime);
                $out = Carbon::parse($outTime);
                $data['worked_hours'] = max(0, round($out->diffInMinutes($in) / 60, 2));
            }
        }

        $attendance->update($data);

        return response()->json([
            'message' => 'Attendance updated successfully.',
            'data' => new AttendanceResource($attendance->load(['employee.department', 'corrections'])),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('manage', Attendance::class);
        $attendance = Attendance::findOrFail($id);
        $attendance->delete();

        return response()->json([
            'message' => 'Attendance record deleted.',
        ]);
    }

    public function getConfig(): JsonResponse
    {
        $config = AttendanceConfig::global();

        return response()->json([
            'data' => $config,
        ]);
    }

    public function updateConfig(Request $request): JsonResponse
    {
        $this->authorize('manage', Attendance::class);

        $data = $request->validate([
            'early_checkin_grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'late_checkin_grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'early_checkout_grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'late_checkout_grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'hourly_leave_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'min_hourly_leave_unit_minutes' => ['required', 'integer', 'min:15', 'max:240'],
        ]);

        $config = AttendanceConfig::global();
        $config->update($data);

        return response()->json([
            'message' => 'Attendance configuration updated successfully.',
            'data' => $config,
        ]);
    }

    public function timeline(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $date = $request->input('date', Carbon::today()->toDateString());
        $employeeId = $request->input('employee_id');

        $query = TimeClockEvent::with('employee.department')
            ->whereDate('event_time', $date)
            ->orderBy('event_time');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $events = $query->get();

        // Group events by employee to build timeline segments
        $grouped = $events->groupBy('employee_id')->map(function ($empEvents) {
            $emp = $empEvents->first()->employee;
            $segments = [];
            $openIn = null;

            foreach ($empEvents as $evt) {
                if ($evt->event_type === 'in') {
                    $openIn = $evt;
                } elseif ($evt->event_type === 'out') {
                    $segments[] = [
                        'in_id' => $openIn?->id,
                        'in_time' => $openIn ? Carbon::parse($openIn->event_time)->format('H:i') : null,
                        'out_id' => $evt->id,
                        'out_time' => Carbon::parse($evt->event_time)->format('H:i'),
                        'duration_minutes' => $openIn ? (int) Carbon::parse($openIn->event_time)->diffInMinutes(Carbon::parse($evt->event_time)) : null,
                    ];
                    $openIn = null;
                }
            }

            if ($openIn) {
                $segments[] = [
                    'in_id' => $openIn->id,
                    'in_time' => Carbon::parse($openIn->event_time)->format('H:i'),
                    'out_id' => null,
                    'out_time' => null,
                    'is_ongoing' => true,
                    'duration_minutes' => (int) Carbon::parse($openIn->event_time)->diffInMinutes(now()),
                ];
            }

            return [
                'employee' => [
                    'id' => $emp?->id,
                    'full_name' => $emp?->full_name,
                    'employee_number' => $emp?->employee_number,
                    'department' => $emp?->department?->name,
                    'avatar_url' => $emp?->avatar_url,
                ],
                'events' => $empEvents->map(fn ($e) => [
                    'id' => $e->id,
                    'type' => $e->event_type,
                    'time' => Carbon::parse($e->event_time)->format('H:i:s'),
                    'source' => $e->source,
                    'notes' => $e->notes,
                ]),
                'segments' => $segments,
            ];
        });

        return response()->json([
            'date' => $date,
            'data' => $grouped->values(),
        ]);
    }
}
