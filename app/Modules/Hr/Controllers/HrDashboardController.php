<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\Attendance;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeCertification;
use App\Modules\Hr\Models\JobOpening;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\TrainingSession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class HrDashboardController extends Controller
{
    public function dashboardStats(): JsonResponse
    {
        $today = Carbon::today()->toDateString();
        $in30Days = Carbon::today()->addDays(30)->toDateString();
        $in60Days = Carbon::today()->addDays(60)->toDateString();
        $in90Days = Carbon::today()->addDays(90)->toDateString();

        // 1. Headcount
        $headcount = [
            'total' => Employee::count(),
            'active' => Employee::where('status', 'active')->count(),
            'on_leave' => Employee::where('status', 'on_leave')->count(),
            'suspended' => Employee::where('status', 'suspended')->count(),
            'terminated' => Employee::where('status', 'terminated')->count(),
        ];

        // 2. Today's Workforce Attendance
        $attendancesToday = Attendance::where('date', $today)->get();
        $attendanceStats = [
            'present' => $attendancesToday->whereIn('status', ['present', 'partial'])->count(),
            'late' => $attendancesToday->where('status', 'late')->count(),
            'absent' => $attendancesToday->where('status', 'absent')->count(),
            'on_leave' => $attendancesToday->where('status', 'on_leave')->count(),
            'total_logged' => $attendancesToday->count(),
            'rate' => $headcount['active'] > 0
                ? round(($attendancesToday->whereIn('status', ['present', 'late', 'partial'])->count() / $headcount['active']) * 100)
                : 0,
        ];

        // 3. Certifications Radar
        $certifications = [
            'expired' => EmployeeCertification::query()
                ->where(function ($q) use ($today) {
                    $q->where('status', 'expired')
                        ->orWhere(function ($inner) use ($today) {
                            $inner->whereNotNull('expiry_date')->where('expiry_date', '<', $today);
                        });
                })
                ->count(),
            'expiring_30_days' => EmployeeCertification::where('status', '!=', 'expired')
                ->whereBetween('expiry_date', [$today, $in30Days])
                ->count(),
            'expiring_60_days' => EmployeeCertification::where('status', '!=', 'expired')
                ->whereBetween('expiry_date', [$in30Days, $in60Days])
                ->count(),
            'expiring_90_days' => EmployeeCertification::where('status', '!=', 'expired')
                ->whereBetween('expiry_date', [$in60Days, $in90Days])
                ->count(),
            'valid' => EmployeeCertification::where('status', 'valid')
                ->where(function ($q) use ($today) {
                    $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', $today);
                })
                ->count(),
        ];

        // 4. Leave & Approvals
        $leaveStats = [
            'pending_approval' => LeaveRequest::where('status', 'submitted')->count(),
            'pending_requests' => LeaveRequest::where('status', 'submitted')->count(),
            'approved_today' => LeaveRequest::where('status', 'approved')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->count(),
            'on_leave_today' => LeaveRequest::where('status', 'approved')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->count(),
        ];

        // 5. Recruitment Pipeline
        $recruitmentStats = [
            'open_positions' => JobOpening::where('status', 'published')->sum('openings_count'),
            'candidates_count' => Candidate::whereNotIn('status', ['hired', 'rejected'])->count(),
            'interviews_scheduled' => Candidate::where('status', 'interview')->count(),
            'offers_extended' => Candidate::where('status', 'offer')->count(),
        ];

        // 6. Training
        $trainingStats = [
            'upcoming_sessions' => TrainingSession::where('status', 'scheduled')
                ->where('start_date', '>=', $today)
                ->count(),
            'in_progress' => TrainingSession::where('status', 'in_progress')->count(),
        ];

        return response()->json([
            'data' => [
                'headcount' => $headcount,
                'today_attendance' => $attendanceStats,
                'certifications' => $certifications,
                'expiring_certifications_count' => $certifications['expiring_30_days'] + $certifications['expiring_60_days'],
                'leave' => $leaveStats,
                'leaves' => $leaveStats,
                'recruitment' => $recruitmentStats,
                'training' => $trainingStats,
            ],
        ]);
    }
}
