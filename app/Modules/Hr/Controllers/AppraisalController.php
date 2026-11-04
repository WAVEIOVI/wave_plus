<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\AppraisalCycle;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeAppraisal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppraisalController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function kpis(Request $request): JsonResponse
    {
        $this->authorizeAppraisalRead();

        $totalCycles = AppraisalCycle::count();
        $activeCycles = AppraisalCycle::where('status', 'active')->count();

        $appraisalQuery = EmployeeAppraisal::query();
        if ($cycleId = $request->input('cycle_id')) {
            $appraisalQuery->where('appraisal_cycle_id', $cycleId);
        }

        $totalAppraisals = (clone $appraisalQuery)->count();
        $completedAppraisals = (clone $appraisalQuery)->where('status', 'acknowledged')->count();
        $inReviewAppraisals = (clone $appraisalQuery)->whereIn('status', ['submitted', 'reviewed'])->count();
        $draftAppraisals = (clone $appraisalQuery)->where('status', 'draft')->count();

        $completionRate = $totalAppraisals > 0
            ? round(($completedAppraisals / $totalAppraisals) * 100, 1)
            : 0;

        $averageScore = round((float) ((clone $appraisalQuery)->whereNotNull('overall_score')->avg('overall_score') ?? 0), 2);

        return response()->json([
            'data' => [
                'total_cycles' => $totalCycles,
                'active_cycles' => $activeCycles,
                'total_appraisals' => $totalAppraisals,
                'completed_appraisals' => $completedAppraisals,
                'in_review_appraisals' => $inReviewAppraisals,
                'draft_appraisals' => $draftAppraisals,
                'completion_rate' => $completionRate,
                'average_score' => $averageScore,
            ],
        ]);
    }

    public function cycles(): JsonResponse
    {
        $this->authorizeAppraisalRead();

        $cycles = AppraisalCycle::withCount('appraisals')->orderByDesc('start_date')->get();

        return response()->json([
            'data' => $cycles,
        ]);
    }

    public function storeCycle(Request $request): JsonResponse
    {
        $this->authorizeAppraisalMutation();

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

    public function showCycle(string $id): JsonResponse
    {
        $this->authorizeAppraisalRead();
        $cycle = AppraisalCycle::withCount('appraisals')->findOrFail($id);

        return response()->json([
            'data' => $cycle,
        ]);
    }

    public function updateCycle(Request $request, string $id): JsonResponse
    {
        $this->authorizeAppraisalMutation();
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
        $this->authorizeAppraisalMutation();
        $cycle = AppraisalCycle::findOrFail($id);

        if ($cycle->appraisals()->exists()) {
            abort(422, 'Cannot delete appraisal cycle with existing employee evaluations.');
        }

        $cycle->delete();

        return response()->json([
            'message' => 'Appraisal cycle deleted.',
        ]);
    }

    public function appraisals(Request $request): JsonResponse
    {
        $user = $request->user();
        $isManager = $user && (
            $user->hasRole(['Super Admin', 'Admin', 'HR Manager'])
            || $user->can('hr.appraisals.view')
            || $user->can('hr.appraisals.manage')
        );

        $query = EmployeeAppraisal::with(['employee.department', 'cycle', 'reviewer', 'acknowledger']);

        if (! $isManager) {
            $linked = Employee::where('user_id', $user?->id)->first();
            if ($linked) {
                $query->where('employee_id', $linked->id);
            } else {
                abort(403, 'You do not have permission to view performance appraisals.');
            }
        } elseif ($empId = $request->input('employee_id')) {
            $query->where('employee_id', $empId);
        }

        if ($cycleId = $request->input('cycle_id')) {
            $query->where('appraisal_cycle_id', $cycleId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $appraisals = $query->orderByDesc('created_at')->get();

        return response()->json([
            'data' => $appraisals,
        ]);
    }

    public function storeAppraisal(Request $request): JsonResponse
    {
        $this->authorizeAppraisalMutation();

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

        $existing = EmployeeAppraisal::where('appraisal_cycle_id', $data['appraisal_cycle_id'])
            ->where('employee_id', $data['employee_id'])
            ->first();

        if ($existing && $existing->status === 'acknowledged') {
            abort(422, 'This appraisal has been acknowledged and finalized. Completed evaluations cannot be modified.');
        }

        // Auto-compute overall_score from ratings if ratings are provided
        if (! empty($data['ratings']) && is_array($data['ratings'])) {
            $numericRatings = array_filter($data['ratings'], fn ($val) => is_numeric($val));
            if (count($numericRatings) > 0) {
                $computedScore = round(array_sum($numericRatings) / count($numericRatings), 2);
                $data['overall_score'] = $data['overall_score'] ?? $computedScore;
            }
        }

        if (isset($data['status']) && $data['status'] === 'reviewed') {
            $data['reviewed_at'] = now();
        }

        if (isset($data['status']) && $data['status'] === 'acknowledged') {
            $data['acknowledged_at'] = now();
            $data['acknowledged_by'] = $request->user()?->id;
        }

        $appraisal = EmployeeAppraisal::updateOrCreate(
            ['appraisal_cycle_id' => $data['appraisal_cycle_id'], 'employee_id' => $data['employee_id']],
            $data
        );

        return response()->json([
            'message' => 'Employee appraisal saved.',
            'data' => $appraisal->load(['employee.department', 'cycle', 'reviewer', 'acknowledger']),
        ], 201);
    }

    public function showAppraisal(string $id): JsonResponse
    {
        $appraisal = EmployeeAppraisal::with(['employee.department', 'cycle', 'reviewer', 'acknowledger'])->findOrFail($id);
        $user = request()->user();
        $isSelf = $user && $appraisal->employee?->user_id === $user->id;
        $isReviewer = $user && $appraisal->reviewer_id === $user->id;

        if (! $isSelf && ! $isReviewer) {
            $this->authorizeAppraisalRead();
        }

        return response()->json([
            'data' => $appraisal,
        ]);
    }

    public function updateAppraisal(Request $request, string $id): JsonResponse
    {
        $this->authorizeAppraisalMutation();
        $appraisal = EmployeeAppraisal::findOrFail($id);

        if ($appraisal->status === 'acknowledged') {
            abort(422, 'This appraisal has been acknowledged and finalized. Completed evaluations cannot be modified.');
        }

        $data = $request->validate([
            'reviewer_id' => ['ulid', 'nullable', 'exists:users,id'],
            'overall_score' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'status' => ['nullable', 'in:draft,submitted,reviewed,acknowledged'],
            'manager_feedback' => ['nullable', 'string'],
            'employee_feedback' => ['nullable', 'string'],
            'goals' => ['nullable', 'array'],
            'ratings' => ['nullable', 'array'],
        ]);

        if (! empty($data['ratings']) && is_array($data['ratings'])) {
            $numericRatings = array_filter($data['ratings'], fn ($val) => is_numeric($val));
            if (count($numericRatings) > 0) {
                $computedScore = round(array_sum($numericRatings) / count($numericRatings), 2);
                $data['overall_score'] = $data['overall_score'] ?? $computedScore;
            }
        }

        if (isset($data['status']) && $data['status'] === 'reviewed' && ! $appraisal->reviewed_at) {
            $data['reviewed_at'] = now();
        }

        if (isset($data['status']) && $data['status'] === 'acknowledged' && ! $appraisal->acknowledged_at) {
            $data['acknowledged_at'] = now();
            $data['acknowledged_by'] = $request->user()?->id;
        }

        $appraisal->update($data);

        return response()->json([
            'message' => 'Employee appraisal updated.',
            'data' => $appraisal->fresh(['employee.department', 'cycle', 'reviewer', 'acknowledger']),
        ]);
    }

    public function acknowledge(Request $request, string $id): JsonResponse
    {
        $appraisal = EmployeeAppraisal::with(['employee', 'cycle', 'reviewer'])->findOrFail($id);
        $user = $request->user();

        $isSelf = $user && $appraisal->employee?->user_id === $user->id;
        $canManage = $user && (
            $user->hasRole(['Super Admin', 'Admin', 'HR Manager'])
            || $user->can('hr.appraisals.manage')
        );

        abort_unless($isSelf || $canManage, 403, 'You are not authorized to acknowledge this appraisal.');

        if ($appraisal->status === 'acknowledged') {
            abort(422, 'This appraisal has already been acknowledged.');
        }

        if (! in_array($appraisal->status, ['submitted', 'reviewed'], true)) {
            abort(422, 'Only submitted or reviewed appraisals can be acknowledged.');
        }

        $data = $request->validate([
            'employee_feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $updateData = [
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $user?->id,
        ];

        if (array_key_exists('employee_feedback', $data) && $data['employee_feedback'] !== null) {
            $updateData['employee_feedback'] = $data['employee_feedback'];
        }

        $appraisal->update($updateData);

        return response()->json([
            'message' => 'Appraisal acknowledged successfully.',
            'data' => $appraisal->fresh(['employee.department', 'cycle', 'reviewer', 'acknowledger']),
        ]);
    }

    public function destroyAppraisal(string $id): JsonResponse
    {
        $this->authorizeAppraisalMutation();
        $appraisal = EmployeeAppraisal::findOrFail($id);

        if ($appraisal->status === 'acknowledged') {
            abort(422, 'Cannot delete a completed and acknowledged performance appraisal.');
        }

        $appraisal->delete();

        return response()->json([
            'message' => 'Employee appraisal deleted.',
        ]);
    }

    protected function authorizeAppraisalRead(): void
    {
        $user = request()->user();
        abort_unless($user, 403);
        if ($user->hasRole(['Super Admin', 'Admin', 'HR Manager'])) {
            return;
        }
        abort_unless(
            $user->can('hr.appraisals.view')
                || $user->can('hr.appraisals.manage'),
            403,
            'You do not have permission to view performance appraisals.'
        );
    }

    protected function authorizeAppraisalMutation(): void
    {
        $user = request()->user();
        abort_unless($user, 403);
        if ($user->hasRole(['Super Admin', 'Admin', 'HR Manager'])) {
            return;
        }
        abort_unless(
            $user->can('hr.appraisals.manage'),
            403,
            'You do not have permission to manage performance appraisals.'
        );
    }
}
