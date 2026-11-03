<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\AttendanceException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceExceptionController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.attendance.view');

        $query = AttendanceException::with([
            'employee.department',
            'clockEvent',
            'attendance',
            'coveringLeave.leaveType',
            'reviewedBy',
        ]);

        if ($empId = $request->input('employee_id')) {
            $query->where('employee_id', $empId);
        }

        if ($status = $request->input('review_status')) {
            $query->where('review_status', $status);
        }

        if ($type = $request->input('exception_type')) {
            $query->where('exception_type', $type);
        }

        if ($date = $request->input('date')) {
            $query->where('date', $date);
        } elseif ($startDate = $request->input('start_date')) {
            $endDate = $request->input('end_date', $startDate);
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        $exceptions = $query->orderByDesc('date')->orderByDesc('created_at')->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => $exceptions->items(),
            'meta' => [
                'current_page' => $exceptions->currentPage(),
                'per_page' => $exceptions->perPage(),
                'total' => $exceptions->total(),
                'last_page' => $exceptions->lastPage(),
                'open_count' => AttendanceException::where('review_status', AttendanceException::REVIEW_OPEN)->count(),
            ],
        ]);
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.attendance.correct');
        $exception = AttendanceException::findOrFail($id);

        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $exception->update([
            'review_status' => AttendanceException::REVIEW_RESOLVED,
            'notes' => $request->input('notes') ?? $exception->notes,
            'reviewed_by_id' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Attendance exception marked as resolved.',
            'data' => $exception->load(['employee', 'reviewedBy']),
        ]);
    }

    public function dismiss(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.attendance.correct');
        $exception = AttendanceException::findOrFail($id);

        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $exception->update([
            'review_status' => AttendanceException::REVIEW_DISMISSED,
            'notes' => $request->input('notes') ?? $exception->notes,
            'reviewed_by_id' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Attendance exception dismissed.',
            'data' => $exception->load(['employee', 'reviewedBy']),
        ]);
    }

    public function acknowledge(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.attendance.correct');
        $exception = AttendanceException::findOrFail($id);

        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $exception->update([
            'review_status' => AttendanceException::REVIEW_ACKNOWLEDGED,
            'notes' => $request->input('notes') ?? $exception->notes,
            'reviewed_by_id' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Attendance exception acknowledged.',
            'data' => $exception->load(['employee', 'reviewedBy']),
        ]);
    }
}
