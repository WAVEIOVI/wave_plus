<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Requests\StoreLeaveRequestRequest;
use App\Modules\Hr\Resources\LeaveRequestResource;
use App\Modules\Hr\Services\HrAuditService;
use App\Modules\Hr\Services\LeaveBalanceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LeaveBalanceService $balanceService,
        protected HrAuditService $auditService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $user = $request->user();
        $isGlobalViewer = $user && ($user->hasRole(['Super Admin', 'Admin']) || $user->can('hr.leave.view') || $user->can('read-PeopleSkills'));

        $query = LeaveRequest::with(['employee.department', 'leaveType', 'approver']);

        if (! $isGlobalViewer) {
            $linked = \App\Modules\Hr\Models\Employee::where('user_id', $user?->id)->first();
            if ($linked) {
                $query->where('employee_id', $linked->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($empId = $request->input('employee_id')) {
            $query->where('employee_id', $empId);
        }

        if ($deptId = $request->input('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        }

        if ($status = $request->input('status')) {
            if ($status === 'pending') {
                $query->whereIn('status', ['submitted', 'pending']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($leaveTypeId = $request->input('leave_type_id')) {
            $query->where('leave_type_id', $leaveTypeId);
        }

        if ($startDateFrom = $request->input('start_date_from', $request->input('date_from'))) {
            $query->whereDate('start_date', '>=', $startDateFrom);
        }

        if ($startDateTo = $request->input('start_date_to', $request->input('date_to'))) {
            $query->whereDate('end_date', '<=', $startDateTo);
        }

        if ($search = trim((string) $request->input('search', $request->input('q', '')))) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($empQ) use ($search) {
                        $empQ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%");
                    });
            });
        }

        $sortBy = $request->input('sort_by', $request->input('sort', 'start_date'));
        $sortOrder = strtolower((string) $request->input('sort_order', $request->input('order', 'desc'))) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['created_at', 'start_date', 'end_date', 'total_days', 'status', 'reference'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('created_at');
        }

        $requests = $query->paginate((int) $request->input('per_page', 15));

        $today = now()->toDateString();
        $stats = [
            'pending_approval' => LeaveRequest::whereIn('status', ['submitted', 'pending'])->count(),
            'approved' => LeaveRequest::where('status', 'approved')->count(),
            'on_leave_today' => LeaveRequest::where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->count(),
            'rejected' => LeaveRequest::where('status', 'rejected')->count(),
            'total' => LeaveRequest::count(),
            'total_days_approved' => (float) LeaveRequest::where('status', 'approved')->sum('total_days'),
        ];

        return response()->json([
            'data' => LeaveRequestResource::collection($requests->getCollection())->resolve(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'last_page' => $requests->lastPage(),
                'stats' => $stats,
            ],
            'stats' => $stats,
        ]);
    }

    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $data = $request->validated();
        unset($data['submit']);
        $data['status'] = 'draft';
        $data['requested_by_id'] = auth()->id();

        $leaveRequest = LeaveRequest::create($data);

        if ($request->boolean('submit', true)) {
            try {
                $this->balanceService->reserveDays($leaveRequest);
            } catch (\InvalidArgumentException $e) {
                $leaveRequest->delete();

                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['days_requested' => [$e->getMessage()]],
                ], 422);
            }

            $leaveRequest->update(['status' => 'submitted']);
            $this->auditService->log($leaveRequest, 'SUBMIT_LEAVE_REQUEST');
        }

        return response()->json([
            'message' => $leaveRequest->status === 'submitted'
                ? 'Leave request submitted for approval.'
                : 'Leave request draft created.',
            'data' => new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::with(['employee.department', 'leaveType', 'approver'])->findOrFail($id);
        $this->authorize('view', $leaveRequest);

        return response()->json([
            'data' => new LeaveRequestResource($leaveRequest),
        ]);
    }

    public function submit(string $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $this->authorize('submit', $leaveRequest);

        if ($leaveRequest->status !== 'draft') {
            return response()->json(['message' => 'Only draft requests can be submitted.'], 422);
        }

        try {
            // Reserve days and verify balance
            $this->balanceService->reserveDays($leaveRequest);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['days_requested' => [$e->getMessage()]],
            ], 422);
        }

        $leaveRequest->update(['status' => 'submitted']);
        $this->auditService->log($leaveRequest, 'SUBMIT_LEAVE_REQUEST');

        return response()->json([
            'message' => 'Leave request submitted for approval.',
            'status' => 'submitted',
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::with('employee')->findOrFail($id);
        $this->authorize('approve', $leaveRequest);

        abort_if(
            ($leaveRequest->employee?->user_id === auth()->id() || $leaveRequest->requested_by_id === auth()->id())
            && ! auth()->user()->hasRole(['Super Admin', 'Admin']),
            403,
            'You cannot approve your own leave request.'
        );

        if ($leaveRequest->status !== 'submitted') {
            return response()->json(['message' => 'Only submitted requests can be approved.'], 422);
        }

        $this->balanceService->approveDays($leaveRequest);

        $decisionNote = $request->input('decision_note', $request->input('notes'));

        $leaveRequest->update([
            'status' => 'approved',
            'approver_id' => auth()->id(),
            'approved_at' => now(),
            'decision_note' => $decisionNote,
        ]);

        // Reconcile and synchronize schedule & attendance coverage without deleting schedules
        $coverageStatus = $leaveRequest->isHourlyLeave()
            ? \App\Modules\Hr\Models\Schedule::COVERAGE_PARTIAL_LEAVE
            : \App\Modules\Hr\Models\Schedule::COVERAGE_FULL_DAY_LEAVE;

        \App\Modules\Hr\Models\Schedule::where('employee_id', $leaveRequest->employee_id)
            ->whereDate('date', '>=', $leaveRequest->start_date->toDateString())
            ->whereDate('date', '<=', $leaveRequest->end_date->toDateString())
            ->update(['coverage_status' => $coverageStatus]);

        \App\Modules\Hr\Models\Attendance::where('employee_id', $leaveRequest->employee_id)
            ->whereDate('date', '>=', $leaveRequest->start_date->toDateString())
            ->whereDate('date', '<=', $leaveRequest->end_date->toDateString())
            ->update([
                'coverage_status' => $coverageStatus,
                'covering_leave_id' => $leaveRequest->id,
            ]);

        // Recalculate attendance records for dates within the leave period that already exist
        $existingAttendances = \App\Modules\Hr\Models\Attendance::where('employee_id', $leaveRequest->employee_id)
            ->whereDate('date', '>=', $leaveRequest->start_date->toDateString())
            ->whereDate('date', '<=', $leaveRequest->end_date->toDateString())
            ->get();

        $calcService = app(\App\Modules\Hr\Services\AttendanceCalculationService::class);
        foreach ($existingAttendances as $att) {
            $calcService->recalculateAttendanceForDate($leaveRequest->employee_id, $att->date->toDateString());
        }

        $this->auditService->log($leaveRequest, 'APPROVE_LEAVE_REQUEST', null, null, $decisionNote);

        return response()->json([
            'message' => 'Leave request approved and schedules reconciled.',
            'status' => 'approved',
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $this->authorize('approve', $leaveRequest);

        $reason = $request->input('rejection_reason', $request->input('reason'));
        if (! $reason || ! trim((string) $reason)) {
            return response()->json([
                'message' => 'The rejection reason is required.',
                'errors' => ['rejection_reason' => ['The rejection reason is required.']],
            ], 422);
        }

        if ($leaveRequest->status === 'submitted') {
            $this->balanceService->releaseReservedDays($leaveRequest);
        }

        $leaveRequest->update([
            'status' => 'rejected',
            'approver_id' => auth()->id(),
            'rejection_reason' => $reason,
            'decision_note' => $reason,
        ]);

        $this->auditService->log($leaveRequest, 'REJECT_LEAVE_REQUEST', null, null, $reason);

        return response()->json([
            'message' => 'Leave request rejected.',
            'status' => 'rejected',
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $this->authorize('cancel', $leaveRequest);

        if (! in_array($leaveRequest->status, ['draft', 'submitted', 'approved'])) {
            return response()->json(['message' => 'Cannot cancel this request.'], 422);
        }

        $wasApproved = $leaveRequest->status === 'approved';
        $wasSubmitted = $leaveRequest->status === 'submitted';

        $leaveRequest->update(['status' => 'cancelled']);
        $this->auditService->log($leaveRequest, 'CANCEL_LEAVE_REQUEST');

        if ($wasSubmitted) {
            $this->balanceService->releaseReservedDays($leaveRequest);
        } elseif ($wasApproved) {
            // Restore approved used days
            $year = (int) $leaveRequest->start_date->format('Y');
            $balance = $this->balanceService->getOrCreateBalance($leaveRequest->employee_id, $leaveRequest->leave_type_id, $year);
            $balance->approved_used_days = max(0, $balance->approved_used_days - $leaveRequest->total_days);
            $balance->recalculate();

            // Revert coverage on schedules and attendance
            \App\Modules\Hr\Models\Schedule::where('employee_id', $leaveRequest->employee_id)
                ->whereDate('date', '>=', $leaveRequest->start_date->toDateString())
                ->whereDate('date', '<=', $leaveRequest->end_date->toDateString())
                ->update(['coverage_status' => \App\Modules\Hr\Models\Schedule::COVERAGE_NORMAL]);

            \App\Modules\Hr\Models\Attendance::where('employee_id', $leaveRequest->employee_id)
                ->where('covering_leave_id', $leaveRequest->id)
                ->update([
                    'coverage_status' => 'normal',
                    'covering_leave_id' => null,
                ]);

            // Recalculate attendance records to restore original working time baseline
            $cancelledAttendances = \App\Modules\Hr\Models\Attendance::where('employee_id', $leaveRequest->employee_id)
                ->whereDate('date', '>=', $leaveRequest->start_date->toDateString())
                ->whereDate('date', '<=', $leaveRequest->end_date->toDateString())
                ->get();

            $calcService = app(\App\Modules\Hr\Services\AttendanceCalculationService::class);
            foreach ($cancelledAttendances as $att) {
                $calcService->recalculateAttendanceForDate($leaveRequest->employee_id, $att->date->toDateString());
            }
        }

        return response()->json([
            'message' => 'Leave request cancelled.',
            'status' => 'cancelled',
        ]);
    }

    public function update(StoreLeaveRequestRequest $request, string $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $this->authorize('update', $leaveRequest);

        if ($leaveRequest->status !== 'draft') {
            return response()->json(['message' => 'Only draft requests can be edited.'], 422);
        }

        $data = $request->validated();
        unset($data['submit']);
        $leaveRequest->update($data);

        if ($request->boolean('submit', false)) {
            try {
                $this->balanceService->reserveDays($leaveRequest);
                $leaveRequest->update(['status' => 'submitted']);
                $this->auditService->log($leaveRequest, 'SUBMIT_LEAVE_REQUEST');
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['days_requested' => [$e->getMessage()]],
                ], 422);
            }
        }

        return response()->json([
            'message' => 'Leave request updated.',
            'data' => new LeaveRequestResource($leaveRequest->fresh(['employee.department', 'leaveType'])),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        $this->authorize('delete', $leaveRequest);

        if (! in_array($leaveRequest->status, ['draft', 'cancelled'], true)) {
            return response()->json(['message' => 'Only draft or cancelled requests can be deleted.'], 422);
        }

        $leaveRequest->delete();

        return response()->json([
            'message' => 'Leave request deleted.',
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $query = LeaveRequest::with(['employee.department', 'leaveType'])
            ->whereIn('status', ['approved', 'submitted', 'pending'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startDate, $endDate) {
                        $sub->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            });

        if ($deptId = $request->input('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $deptId));
        }

        if ($empId = $request->input('employee_id')) {
            $query->where('employee_id', $empId);
        }

        $events = $query->get()->map(fn ($lr) => [
            'id' => $lr->id,
            'title' => ($lr->employee?->full_name ?? 'Staff').' — '.($lr->leaveType?->name ?? 'Leave'),
            'start' => $lr->start_date->toDateString(),
            'end' => $lr->end_date->toDateString(),
            'status' => $lr->status,
            'color' => $lr->status === 'approved' ? 'success' : 'warning',
            'employee_name' => $lr->employee?->full_name,
            'employee_id' => $lr->employee_id,
            'avatar_url' => $lr->employee?->avatar_url,
            'department' => $lr->employee?->department?->name,
            'leave_type' => $lr->leaveType?->name,
            'days' => $lr->total_days,
            'reason' => $lr->reason,
        ]);

        return response()->json([
            'data' => $events,
        ]);
    }
}
