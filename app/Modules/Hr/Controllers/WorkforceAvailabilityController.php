<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\Schedule;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Resources\ShiftResource;
use App\Modules\Hr\Requests\WorkforceQueryRequest;
use App\Modules\Hr\Services\WorkforceAvailabilityService;
use App\Modules\Hr\Support\TranslatableFields;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkforceAvailabilityController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function __construct(
        protected WorkforceAvailabilityService $availabilityService
    ) {}

    /**
     * Strategic Workforce Query Endpoint.
     * Consumed by Operational Hub, Workshop, and Logistics.
     */
    public function queryAvailability(WorkforceQueryRequest $request): JsonResponse
    {
        $this->authorizeHrRead('hr.workforce.view');

        $startTime = Carbon::parse($request->input('start_time'));
        $endTime = Carbon::parse($request->input('end_time'));
        $skillIds = $request->input('required_skill_ids', []);
        $certIds = $request->input('required_certification_ids', []);
        $teamId = $request->input('team_id');
        $departmentId = $request->input('department_id');

        $candidates = $this->availabilityService->queryAvailability(
            startTime: $startTime,
            endTime: $endTime,
            requiredSkillIds: $skillIds,
            requiredCertificationIds: $certIds,
            teamId: $teamId,
            departmentId: $departmentId
        );

        return response()->json([
            'meta' => [
                'start_time' => $startTime->toISOString(),
                'end_time' => $endTime->toISOString(),
                'total_evaluated' => $candidates->count(),
                'total_available' => $candidates->where('is_available', true)->count(),
            ],
            'data' => $candidates->values(),
        ]);
    }

    public function shifts(): JsonResponse
    {
        $this->authorizeHrRead('hr.workforce.view');

        $shifts = Shift::active()->orderBy('start_time')->get();

        return response()->json([
            'data' => ShiftResource::collection($shifts)->resolve(),
        ]);
    }

    public function storeShift(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        TranslatableFields::normalizeRequest($request, ['name']);

        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:hr_shifts,code'],
            'start_time' => ['required', 'date_format:H:i:s,H:i'],
            'end_time' => ['required', 'date_format:H:i:s,H:i'],
            'break_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'is_overnight' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', true, 100)));

        $shift = Shift::create($data);

        return response()->json([
            'message' => 'Shift created.',
            'data' => new ShiftResource($shift),
        ], 201);
    }

    public function updateShift(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        $shift = Shift::findOrFail($id);
        TranslatableFields::normalizeRequest($request, ['name']);

        $data = $request->validate(array_merge([
            'code' => ['sometimes', 'required', 'string', 'max:50', "unique:hr_shifts,code,{$id}"],
            'start_time' => ['sometimes', 'required', 'date_format:H:i:s,H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i:s,H:i'],
            'break_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'is_overnight' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', false, 100, true)));

        $shift->update($data);

        return response()->json([
            'message' => 'Shift updated successfully.',
            'data' => new ShiftResource($shift),
        ]);
    }

    public function destroyShift(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        $shift = Shift::withCount('schedules')->findOrFail($id);

        if ($shift->schedules_count > 0) {
            return response()->json([
                'message' => 'Cannot delete shift that has associated schedules. Please reassign schedules first.',
            ], 422);
        }

        $shift->delete();

        return response()->json([
            'message' => 'Shift deleted successfully.',
        ]);
    }

    public function schedules(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.workforce.view');

        $startDate = $request->input('start_date', now()->startOfWeek()->toDateString());
        $endDate = $request->input('end_date', now()->endOfWeek()->toDateString());

        $query = Schedule::with(['employee.department', 'shift'])
            ->whereBetween('date', [$startDate, $endDate]);

        if ($empId = $request->input('employee_id')) {
            $query->where('employee_id', $empId);
        }

        if ($deptId = $request->input('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        }

        $schedules = $query->orderBy('date')->get();

        return response()->json([
            'data' => $schedules,
        ]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');

        $data = $request->validate([
            'employee_id' => ['ulid', 'required', 'exists:hr_employees,id'],
            'shift_id' => ['ulid', 'required', 'exists:hr_shifts,id'],
            'date' => ['required', 'date'],
            'status' => ['nullable', 'in:scheduled,completed,cancelled,swapped'],
            'lifecycle_status' => ['nullable', 'in:draft,planned,in_progress,completed,cancelled'],
            'custom_start_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'custom_end_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'is_overnight_override' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $schedule = Schedule::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'date' => $data['date']],
            [
                'shift_id' => $data['shift_id'],
                'status' => $data['status'] ?? 'scheduled',
                'lifecycle_status' => $data['lifecycle_status'] ?? 'planned',
                'custom_start_time' => $data['custom_start_time'] ?? null,
                'custom_end_time' => $data['custom_end_time'] ?? null,
                'is_overnight_override' => $data['is_overnight_override'] ?? false,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Schedule assigned successfully.',
            'data' => $schedule->load(['employee', 'shift']),
        ], 201);
    }

    public function updateSchedule(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        $schedule = Schedule::findOrFail($id);

        $data = $request->validate([
            'employee_id' => ['ulid', 'sometimes', 'required', 'exists:hr_employees,id'],
            'shift_id' => ['ulid', 'sometimes', 'required', 'exists:hr_shifts,id'],
            'date' => ['sometimes', 'required', 'date'],
            'status' => ['nullable', 'in:scheduled,completed,cancelled,swapped'],
            'lifecycle_status' => ['nullable', 'in:draft,planned,in_progress,completed,cancelled'],
            'custom_start_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'custom_end_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'is_overnight_override' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $schedule->update($data);

        return response()->json([
            'message' => 'Schedule updated successfully.',
            'data' => $schedule->load(['employee', 'shift']),
        ]);
    }

    public function destroySchedule(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');
        $schedule = Schedule::findOrFail($id);

        if ($schedule->lifecycle_status === Schedule::STATUS_COMPLETED || $schedule->status === 'completed') {
            return response()->json([
                'message' => 'Cannot delete completed schedule.',
            ], 422);
        }

        $schedule->delete();

        return response()->json([
            'message' => 'Schedule removed successfully.',
        ]);
    }
}
