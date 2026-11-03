<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Concerns\AuthorizesHrMutations;
use App\Modules\Hr\Models\LeavePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeavePolicyController extends Controller
{
    use AuthorizesHrMutations;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.leave.view');

        $query = LeavePolicy::with(['leaveType', 'department']);

        if ($leaveTypeId = $request->input('leave_type_id')) {
            $query->where('leave_type_id', $leaveTypeId);
        }

        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        $policies = $query->get();

        return response()->json([
            'data' => $policies,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.leave.manage');

        $data = $request->validate([
            'leave_type_id' => ['required', 'ulid', 'exists:hr_leave_types,id'],
            'department_id' => ['nullable', 'ulid', 'exists:hr_departments,id'],
            'accrual_rate_per_month' => ['required', 'numeric', 'min:0', 'max:31'],
            'max_carryover_days' => ['required', 'numeric', 'min:0', 'max:365'],
        ]);

        $policy = LeavePolicy::create($data);

        return response()->json([
            'message' => 'Leave policy created.',
            'data' => $policy->load(['leaveType', 'department']),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorizeHrRead('hr.leave.view');
        $policy = LeavePolicy::with(['leaveType', 'department'])->findOrFail($id);

        return response()->json([
            'data' => $policy,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.leave.manage');
        $policy = LeavePolicy::findOrFail($id);

        $data = $request->validate([
            'leave_type_id' => ['required', 'ulid', 'exists:hr_leave_types,id'],
            'department_id' => ['nullable', 'ulid', 'exists:hr_departments,id'],
            'accrual_rate_per_month' => ['required', 'numeric', 'min:0', 'max:31'],
            'max_carryover_days' => ['required', 'numeric', 'min:0', 'max:365'],
        ]);

        $policy->update($data);

        return response()->json([
            'message' => 'Leave policy updated.',
            'data' => $policy->fresh()->load(['leaveType', 'department']),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.leave.manage');
        $policy = LeavePolicy::findOrFail($id);
        $policy->delete();

        return response()->json([
            'message' => 'Leave policy deleted.',
        ]);
    }
}
