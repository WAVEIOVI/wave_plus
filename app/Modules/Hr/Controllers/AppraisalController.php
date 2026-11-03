<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\AppraisalCycle;
use App\Modules\Hr\Models\EmployeeAppraisal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppraisalController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function cycles(): JsonResponse
    {
        $this->authorizeHrRead('hr.employees.view');

        $cycles = AppraisalCycle::withCount('appraisals')->orderByDesc('start_date')->get();

        return response()->json([
            'data' => $cycles,
        ]);
    }

    public function storeCycle(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.employees.edit');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'cycle_type' => ['required', 'in:annual,quarterly,probation,project'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:draft,active,review,completed'],
        ]);

        $cycle = AppraisalCycle::create($data);

        return response()->json([
            'message' => 'Appraisal cycle created.',
            'data' => $cycle,
        ], 201);
    }

    public function appraisals(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.employees.view');

        $query = EmployeeAppraisal::with(['employee.department', 'cycle', 'reviewer']);

        if ($cycleId = $request->input('cycle_id')) {
            $query->where('appraisal_cycle_id', $cycleId);
        }

        if ($empId = $request->input('employee_id')) {
            $query->where('employee_id', $empId);
        }

        $appraisals = $query->orderByDesc('created_at')->get();

        return response()->json([
            'data' => $appraisals,
        ]);
    }

    public function storeAppraisal(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.employees.edit');

        $data = $request->validate([
            'appraisal_cycle_id' => ['ulid', 'required', 'exists:hr_appraisal_cycles,id'],
            'employee_id' => ['ulid', 'required', 'exists:hr_employees,id'],
            'reviewer_id' => ['ulid', 'nullable', 'exists:users,id'],
            'overall_score' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'status' => ['nullable', 'in:draft,submitted,reviewed,acknowledged'],
            'manager_feedback' => ['nullable', 'string'],
            'employee_feedback' => ['nullable', 'string'],
            'goals' => ['nullable', 'array'],
            'ratings' => ['nullable', 'array'],
        ]);

        $appraisal = EmployeeAppraisal::updateOrCreate(
            ['appraisal_cycle_id' => $data['appraisal_cycle_id'], 'employee_id' => $data['employee_id']],
            $data
        );

        return response()->json([
            'message' => 'Employee appraisal saved.',
            'data' => $appraisal->load(['employee', 'cycle', 'reviewer']),
        ], 201);
    }

    public function showCycle(string $id): JsonResponse
    {
        $this->authorizeHrRead('hr.employees.view');
        $cycle = AppraisalCycle::withCount('appraisals')->findOrFail($id);

        return response()->json([
            'data' => $cycle,
        ]);
    }

    public function updateCycle(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.employees.edit');
        $cycle = AppraisalCycle::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'cycle_type' => ['required', 'in:annual,quarterly,probation,project'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:draft,active,review,completed'],
        ]);

        $cycle->update($data);

        return response()->json([
            'message' => 'Appraisal cycle updated.',
            'data' => $cycle,
        ]);
    }

    public function destroyCycle(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.employees.edit');
        $cycle = AppraisalCycle::findOrFail($id);
        $cycle->delete();

        return response()->json([
            'message' => 'Appraisal cycle deleted.',
        ]);
    }

    public function showAppraisal(string $id): JsonResponse
    {
        $this->authorizeHrRead('hr.employees.view');
        $appraisal = EmployeeAppraisal::with(['employee.department', 'cycle', 'reviewer'])->findOrFail($id);

        return response()->json([
            'data' => $appraisal,
        ]);
    }

    public function updateAppraisal(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.employees.edit');
        $appraisal = EmployeeAppraisal::findOrFail($id);

        $data = $request->validate([
            'reviewer_id' => ['ulid', 'nullable', 'exists:users,id'],
            'overall_score' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'status' => ['nullable', 'in:draft,submitted,reviewed,acknowledged'],
            'manager_feedback' => ['nullable', 'string'],
            'employee_feedback' => ['nullable', 'string'],
            'goals' => ['nullable', 'array'],
            'ratings' => ['nullable', 'array'],
        ]);

        $appraisal->update($data);

        return response()->json([
            'message' => 'Employee appraisal updated.',
            'data' => $appraisal->fresh(['employee', 'cycle', 'reviewer']),
        ]);
    }

    public function destroyAppraisal(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.employees.edit');
        $appraisal = EmployeeAppraisal::findOrFail($id);
        $appraisal->delete();

        return response()->json([
            'message' => 'Employee appraisal deleted.',
        ]);
    }
}
