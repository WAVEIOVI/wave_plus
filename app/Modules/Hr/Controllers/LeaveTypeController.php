<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveBalance;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Resources\LeaveTypeResource;
use App\Modules\Hr\Support\TranslatableFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->can('hr.leave.request')) {
            $this->authorizeHrRead('hr.leave.view');
        }

        $query = LeaveType::with('policies.department');

        if (! $request->boolean('all', false)) {
            $query->active();
        }

        $types = $query->orderBy('name->'.app()->getLocale())->get();

        return response()->json([
            'data' => LeaveTypeResource::collection($types)->resolve(),
        ]);
    }

    public function balances(Request $request): JsonResponse
    {
        $employeeId = $this->resolveLeaveBalanceEmployeeId($request);
        $filters = $request->validate([
            'employee_id' => ['nullable', 'ulid', 'exists:hr_employees,id'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);
        $year = (int) ($filters['year'] ?? date('Y'));

        $query = LeaveBalance::with(['leaveType', 'employee'])
            ->where('year', $year);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $balances = $query->get()->map(function ($balance) {
            $balance->remaining_days = $balance->available_days;
            $balance->pending_days = $balance->pending_reserved_days;

            return $balance;
        });

        return response()->json([
            'data' => $balances,
        ]);
    }

    private function resolveLeaveBalanceEmployeeId(Request $request): ?string
    {
        $user = $request->user();
        abort_unless($user, 403);

        $hasGlobalAccess = $user->hasRole(['Super Admin', 'Admin'])
            || $user->can('hr.leave.view')
            || $user->can('hr.leave.manage')
            || $user->can('hr.leave.archive')
            || $user->can('read-PeopleSkills');

        if ($hasGlobalAccess) {
            $this->authorizeHrRead('hr.leave.view');

            return $request->input('employee_id');
        }

        abort_unless($user->can('hr.leave.request'), 403, 'You do not have permission to view leave balances.');

        $employee = Employee::where('user_id', $user->id)->first();
        abort_unless($employee, 403, 'No linked employee record found for the current user.');

        $requestedEmployeeId = $request->input('employee_id');
        abort_unless(
            ! $requestedEmployeeId || $requestedEmployeeId === $employee->id,
            403,
            'You can only view your own leave balance.'
        );

        return $employee->id;
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.leave.manage');
        TranslatableFields::normalizeRequest($request, ['name', 'description']);

        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:hr_leave_types,code'],
            'is_paid' => ['nullable', 'boolean'],
            'max_days_per_year' => ['required', 'numeric', 'min:0'],
            'requires_approval' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', true, 100), TranslatableFields::rules('description')));

        $leaveType = LeaveType::create($data);

        return response()->json([
            'message' => 'Leave type created.',
            'data' => new LeaveTypeResource($leaveType),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeHrRead('hr.leave.view');
        $leaveType = LeaveType::with('policies.department')->findOrFail($id);

        return response()->json([
            'data' => new LeaveTypeResource($leaveType),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.leave.manage');
        $leaveType = LeaveType::findOrFail($id);
        TranslatableFields::normalizeRequest($request, ['name', 'description']);

        $data = $request->validate(array_merge([
            'code' => ['required', 'string', 'max:50', 'unique:hr_leave_types,code,'.$id],
            'is_paid' => ['nullable', 'boolean'],
            'max_days_per_year' => ['required', 'numeric', 'min:0'],
            'requires_approval' => ['nullable', 'boolean'],
            'allows_hourly' => ['nullable', 'boolean'],
            'min_hourly_unit_minutes' => ['nullable', 'integer', 'min:5'],
            'max_hours_per_request' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ], TranslatableFields::rules('name', true, 100), TranslatableFields::rules('description')));

        $leaveType->update($data);

        return response()->json([
            'message' => 'Leave type updated.',
            'data' => new LeaveTypeResource($leaveType->fresh()->load('policies.department')),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.leave.manage');
        $leaveType = LeaveType::findOrFail($id);
        $leaveType->delete();

        return response()->json([
            'message' => 'Leave type deleted.',
        ]);
    }

    public function adjustBalance(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.leave.manage');

        $data = $request->validate([
            'employee_id' => ['required', 'ulid', 'exists:hr_employees,id'],
            'leave_type_id' => ['required', 'ulid', 'exists:hr_leave_types,id'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'accrued_days' => ['nullable', 'numeric', 'min:0'],
            'approved_used_days' => ['nullable', 'numeric', 'min:0'],
            'pending_reserved_days' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $balance = LeaveBalance::firstOrNew([
            'employee_id' => $data['employee_id'],
            'leave_type_id' => $data['leave_type_id'],
            'year' => $data['year'],
        ]);

        if (isset($data['accrued_days'])) {
            $balance->accrued_days = (float) $data['accrued_days'];
        }
        if (isset($data['approved_used_days'])) {
            $balance->approved_used_days = (float) $data['approved_used_days'];
        }
        if (isset($data['pending_reserved_days'])) {
            $balance->pending_reserved_days = (float) $data['pending_reserved_days'];
        }

        $balance->recalculate();

        return response()->json([
            'message' => 'Leave balance adjusted successfully.',
            'data' => $balance->load(['leaveType', 'employee']),
        ]);
    }
}
