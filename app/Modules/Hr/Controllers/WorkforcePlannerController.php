<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Services\WorkforcePlannerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkforcePlannerController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function __construct(
        protected WorkforcePlannerService $plannerService
    ) {}

    public function plannerGrid(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.workforce.view');

        $startDate = $request->input('start_date', Carbon::now()->startOfWeek()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfWeek()->toDateString());
        $departmentId = $request->input('department_id');
        $teamId = $request->input('team_id');
        $search = $request->input('search');

        $grid = $this->plannerService->getPlannerGrid(
            startDate: $startDate,
            endDate: $endDate,
            departmentId: $departmentId,
            teamId: $teamId,
            search: $search
        );

        return response()->json($grid);
    }

    public function bulkSchedule(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');

        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'ulid', 'exists:hr_employees,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'shift_id' => ['required', 'ulid', 'exists:hr_shifts,id'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'lifecycle_status' => ['nullable', 'in:draft,planned'],
            'skip_approved_leaves' => ['nullable', 'boolean'],
            'skip_holidays' => ['nullable', 'boolean'],
            'include_weekends' => ['nullable', 'boolean'],
            'overwrite_existing' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->plannerService->bulkSchedule($data);

        return response()->json([
            'message' => 'Bulk schedules processed successfully.',
            'data' => $result,
        ], 201);
    }

    public function publishSchedules(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');

        $data = $request->validate([
            'schedule_ids' => ['nullable', 'array'],
            'schedule_ids.*' => ['ulid', 'exists:hr_schedules,id'],
            'batch_id' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'employee_ids' => ['nullable', 'array'],
        ]);

        $count = $this->plannerService->publishSchedules($data);

        return response()->json([
            'message' => "{$count} schedules published to planned.",
            'published_count' => $count,
        ]);
    }

    public function clearSchedules(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.workforce.assign');

        $data = $request->validate([
            'schedule_ids' => ['nullable', 'array'],
            'schedule_ids.*' => ['ulid', 'exists:hr_schedules,id'],
            'batch_id' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'employee_ids' => ['nullable', 'array'],
        ]);

        $count = $this->plannerService->clearSchedules($data);

        return response()->json([
            'message' => "{$count} schedules removed.",
            'deleted_count' => $count,
        ]);
    }
}
