<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Models\User;
use App\Modules\Hr\Models\AppraisalCycle;
use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\Attendance;
use App\Modules\Hr\Models\AttendanceCorrection;
use App\Modules\Hr\Models\AttendanceException;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeAppraisal;
use App\Modules\Hr\Models\EmployeeCertification;
use App\Modules\Hr\Models\EmployeeSkill;
use App\Modules\Hr\Models\EmployeeTraining;
use App\Modules\Hr\Models\Interview;
use App\Modules\Hr\Models\JobOpening;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\Schedule;
use App\Modules\Hr\Models\Skill;
use App\Modules\Hr\Models\TrainingProgram;
use App\Modules\Hr\Models\TrainingSession;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleActivityLog;
use Carbon\Carbon;

class HrDashboardService
{
    /**
     * Build the aggregated presentation-ready HR dashboard payload using real database queries.
     */
    public function statistics(?User $user = null): array
    {
        $today = Carbon::today()->toDateString();
        $in30Days = Carbon::today()->addDays(30)->toDateString();
        $in60Days = Carbon::today()->addDays(60)->toDateString();
        $in90Days = Carbon::today()->addDays(90)->toDateString();

        // 1. Headcount & Department Distribution
        $headcount = $this->headcountStatistics();

        // 2. Attendance & Today's Presence
        $attendance = $this->attendanceStatistics($today, $headcount['active']);

        // 3. Leave & Pending Approvals
        $leave = $this->leaveStatistics($today);

        // 4. Workforce Planning & Coverage
        $workforce = $this->workforceStatistics($today, $attendance['present']);

        // 5. Recruitment Pipeline
        $recruitment = $this->recruitmentStatistics();

        // 6. Skills & Certifications
        $certifications = $this->certificationsStatistics($today, $in30Days, $in60Days, $in90Days);

        // 7. Training Programs & Sessions
        $training = $this->trainingStatistics($today);

        // 8. Performance Appraisals
        $appraisals = $this->appraisalStatistics();

        // 9. Fleet Overview & Alerts
        $fleet = $this->fleetStatistics($in30Days);

        // 10. Standardized KPIs Row
        $kpis = $this->buildKpis(
            $headcount,
            $attendance,
            $leave,
            $workforce,
            $recruitment,
            $certifications,
            $fleet,
            $appraisals
        );

        // 11. Recent Activity Feed
        $recentActivity = $this->recentActivityFeed();

        return [
            // Standardized Top KPIs
            'kpis' => $kpis,

            // Domain Aggregates
            'headcount' => $headcount,
            'today_attendance' => $attendance,
            'leave' => $leave,
            'leaves' => $leave,
            'workforce' => $workforce,
            'recruitment' => $recruitment,
            'certifications' => $certifications,
            'expiring_certifications_count' => $certifications['expiring_30_days'] + $certifications['expired'],
            'skills_and_certifications' => $certifications,
            'training' => $training,
            'appraisals' => $appraisals,
            'fleet' => $fleet,

            // Recent Real Chronological Events
            'recent_activity' => $recentActivity,
        ];
    }

    private function headcountStatistics(): array
    {
        $total = Employee::count();
        $active = Employee::where('status', 'active')->count();
        $onLeave = Employee::where('status', 'on_leave')->count();
        $suspended = Employee::where('status', 'suspended')->count();
        $terminated = Employee::where('status', 'terminated')->count();

        // Department distribution
        $departments = Department::withCount(['employees' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('employees_count')
            ->limit(8)
            ->get()
            ->map(fn (Department $dept) => [
                'id' => $dept->id,
                'name' => $dept->getTranslations('name'),
                'code' => $dept->code,
                'count' => (int) $dept->employees_count,
                'percentage' => $active > 0 ? round(($dept->employees_count / $active) * 100, 1) : 0,
            ])
            ->values()
            ->all();

        // 6-month monthly hire trend
        $monthlyGrowth = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::today()->subMonths($i);
            $count = Employee::whereYear('hire_date', $month->year)
                ->whereMonth('hire_date', $month->month)
                ->count();

            $monthlyGrowth[] = [
                'month' => $month->format('M Y'),
                'hires' => $count,
            ];
        }

        return [
            'total' => $total,
            'active' => $active,
            'on_leave' => $onLeave,
            'suspended' => $suspended,
            'terminated' => $terminated,
            'department_distribution' => $departments,
            'monthly_growth' => $monthlyGrowth,
        ];
    }

    private function attendanceStatistics(string $today, int $activeEmployees): array
    {
        $attendancesToday = Attendance::where('date', $today)->get();
        $present = $attendancesToday->whereIn('status', ['present', 'partial'])->count();
        $late = $attendancesToday->where('status', 'late')->count();
        $absent = $attendancesToday->where('status', 'absent')->count();
        $onLeave = $attendancesToday->where('status', 'on_leave')->count();
        $totalLogged = $attendancesToday->count();

        $rate = $activeEmployees > 0
            ? round((($present + $late) / $activeEmployees) * 100)
            : 0;

        $pendingExceptions = AttendanceException::open()->count();
        $pendingCorrections = AttendanceCorrection::where('status', 'pending')->count();

        return [
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'on_leave' => $onLeave,
            'total_logged' => $totalLogged,
            'rate' => (int) $rate,
            'pending_exceptions_count' => $pendingExceptions,
            'pending_corrections_count' => $pendingCorrections,
        ];
    }

    private function leaveStatistics(string $today): array
    {
        $pending = LeaveRequest::where('status', 'submitted')->count();

        $onLeaveToday = LeaveRequest::where('status', 'approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();

        $upcoming7Days = LeaveRequest::where('status', 'approved')
            ->where('start_date', '>', $today)
            ->where('start_date', '<=', Carbon::today()->addDays(7)->toDateString())
            ->count();

        return [
            'pending_approval' => $pending,
            'pending_requests' => $pending,
            'approved_today' => $onLeaveToday,
            'on_leave_today' => $onLeaveToday,
            'upcoming_7_days' => $upcoming7Days,
        ];
    }

    private function workforceStatistics(string $today, int $presentCount): array
    {
        $todaySchedules = Schedule::where('date', $today)->get();
        $totalScheduled = $todaySchedules->count();
        $published = $todaySchedules->where('status', 'published')->count();
        $openShifts = $todaySchedules->whereNull('employee_id')->count();

        $coverageRate = $totalScheduled > 0
            ? (int) min(100, round(($presentCount / $totalScheduled) * 100))
            : ($presentCount > 0 ? 100 : 0);

        return [
            'total_scheduled' => $totalScheduled,
            'published_schedules' => $published,
            'open_shifts' => $openShifts,
            'coverage_rate' => $coverageRate,
        ];
    }

    private function recruitmentStatistics(): array
    {
        $openPositions = (int) JobOpening::where('status', 'published')->sum('openings_count');
        $vacanciesCount = JobOpening::where('status', 'published')->count();

        $candidatesByStage = Candidate::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $activeCandidates = Candidate::whereNotIn('status', ['hired', 'rejected'])->count();
        $interviewsScheduled = Interview::where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->count();

        if ($interviewsScheduled === 0) {
            $interviewsScheduled = (int) ($candidatesByStage['interview'] ?? 0);
        }

        return [
            'open_positions' => $openPositions > 0 ? $openPositions : $vacanciesCount,
            'vacancies_count' => $vacanciesCount,
            'candidates_count' => $activeCandidates,
            'interviews_scheduled' => $interviewsScheduled,
            'offers_extended' => (int) ($candidatesByStage['offer'] ?? 0),
            'by_stage' => [
                'applied' => (int) ($candidatesByStage['applied'] ?? 0),
                'screening' => (int) ($candidatesByStage['screening'] ?? 0),
                'interview' => (int) ($candidatesByStage['interview'] ?? 0),
                'offer' => (int) ($candidatesByStage['offer'] ?? 0),
                'hired' => (int) ($candidatesByStage['hired'] ?? 0),
            ],
        ];
    }

    private function certificationsStatistics(string $today, string $in30Days, string $in60Days, string $in90Days): array
    {
        $expired = EmployeeCertification::query()
            ->where(function ($q) use ($today) {
                $q->where('status', 'expired')
                    ->orWhere(function ($inner) use ($today) {
                        $inner->whereNotNull('expiry_date')->where('expiry_date', '<', $today);
                    });
            })
            ->count();

        $expiring30 = EmployeeCertification::where('status', '!=', 'expired')
            ->whereBetween('expiry_date', [$today, $in30Days])
            ->count();

        $expiring60 = EmployeeCertification::where('status', '!=', 'expired')
            ->whereBetween('expiry_date', [$in30Days, $in60Days])
            ->count();

        $expiring90 = EmployeeCertification::where('status', '!=', 'expired')
            ->whereBetween('expiry_date', [$in60Days, $in90Days])
            ->count();

        $valid = EmployeeCertification::where('status', 'valid')
            ->where(function ($q) use ($today) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', $today);
            })
            ->count();

        $totalSkills = Skill::count();
        $verifiedSkills = EmployeeSkill::count();

        return [
            'expired' => $expired,
            'expiring_30_days' => $expiring30,
            'expiring_60_days' => $expiring60,
            'expiring_90_days' => $expiring90,
            'valid' => $valid,
            'total_skills' => $totalSkills,
            'verified_skills' => $verifiedSkills,
        ];
    }

    private function trainingStatistics(string $today): array
    {
        $activePrograms = TrainingProgram::where('is_active', true)->count();

        $upcomingSessions = TrainingSession::where('status', 'scheduled')
            ->where('start_date', '>=', $today)
            ->count();

        $inProgress = TrainingSession::where('status', 'in_progress')->count();
        $enrolled = EmployeeTraining::where('status', 'enrolled')->count();
        $completedYear = EmployeeTraining::where('status', 'completed')
            ->whereYear('completion_date', Carbon::today()->year)
            ->count();

        return [
            'active_programs' => $activePrograms,
            'upcoming_sessions' => $upcomingSessions,
            'in_progress' => $inProgress,
            'enrolled_employees' => $enrolled,
            'completed_this_year' => $completedYear,
        ];
    }

    private function appraisalStatistics(): array
    {
        $activeCycles = AppraisalCycle::where('status', 'active')->count();

        $pendingReviews = EmployeeAppraisal::whereIn('status', ['draft', 'in_progress', 'submitted'])->count();
        $pendingAcknowledgement = EmployeeAppraisal::where('status', 'reviewed')->count();
        $completed = EmployeeAppraisal::where('status', 'acknowledged')->count();

        $avgScore = EmployeeAppraisal::whereNotNull('overall_score')->avg('overall_score');

        return [
            'active_cycles' => $activeCycles,
            'pending_reviews' => $pendingReviews,
            'pending_acknowledgement' => $pendingAcknowledgement,
            'completed' => $completed,
            'average_score' => $avgScore ? round((float) $avgScore, 2) : 0.0,
        ];
    }

    private function fleetStatistics(string $in30Days): array
    {
        $total = Vehicle::count();
        $available = Vehicle::where('status', 'available')->count();
        $inUse = Vehicle::where('status', 'in_use')->count();
        $maintenance = Vehicle::where('status', 'maintenance')->count();
        $rentalActive = Vehicle::where('status', 'rental_active')->count();
        $clientUse = Vehicle::where('status', 'client_use')->count();

        $approvedDrivers = ApprovedDriver::where('authorization_status', 'approved')->count();

        $serviceDue = Vehicle::where(function ($q) use ($in30Days) {
            $q->whereNotNull('next_service_date')->where('next_service_date', '<=', $in30Days)
                ->orWhere(function ($inner) use ($in30Days) {
                    $inner->whereNotNull('next_inspection_date')->where('next_inspection_date', '<=', $in30Days);
                });
        })->count();

        $driverRenewalsDue = ApprovedDriver::where('authorization_status', 'approved')
            ->whereNotNull('license_expiry_date')
            ->where('license_expiry_date', '<=', $in30Days)
            ->count();

        return [
            'total_vehicles' => $total,
            'available' => $available,
            'in_use' => $inUse,
            'maintenance' => $maintenance,
            'rental_active' => $rentalActive,
            'client_use' => $clientUse,
            'approved_drivers' => $approvedDrivers,
            'service_due_count' => $serviceDue,
            'driver_renewals_due' => $driverRenewalsDue,
            'total_alerts' => $serviceDue + $driverRenewalsDue,
        ];
    }

    private function buildKpis(
        array $headcount,
        array $attendance,
        array $leave,
        array $workforce,
        array $recruitment,
        array $certifications,
        array $fleet,
        array $appraisals
    ): array {
        return [
            'employees' => [
                'count' => $headcount['active'],
                'total' => $headcount['total'],
                'label' => 'Active Employees',
                'trend' => 'up',
                'growth' => 0,
            ],
            'attendance' => [
                'count' => $attendance['present'],
                'rate' => $attendance['rate'],
                'label' => "Today's Attendance Rate",
            ],
            'leave' => [
                'count' => $leave['pending_approval'],
                'on_leave_today' => $leave['on_leave_today'],
                'label' => 'Pending Approvals',
            ],
            'workforce' => [
                'count' => $workforce['total_scheduled'],
                'coverage_rate' => $workforce['coverage_rate'],
                'label' => 'Scheduled Today',
            ],
            'recruitment' => [
                'count' => $recruitment['open_positions'],
                'candidates' => $recruitment['candidates_count'],
                'label' => 'Open Vacancies',
            ],
            'certifications' => [
                'count' => $certifications['expiring_30_days'] + $certifications['expired'],
                'expired' => $certifications['expired'],
                'label' => 'Expiring / Expired',
            ],
            'fleet' => [
                'count' => $fleet['total_vehicles'],
                'alerts' => $fleet['total_alerts'],
                'label' => 'Fleet Fleet Vehicles',
            ],
            'appraisals' => [
                'count' => $appraisals['pending_reviews'] + $appraisals['pending_acknowledgement'],
                'avg_score' => $appraisals['average_score'],
                'label' => 'Pending Evaluations',
            ],
        ];
    }

    private function recentActivityFeed(): array
    {
        $activities = collect();

        // Recent Employees (joined)
        $recentEmployees = Employee::with(['department', 'position'])
            ->latest('created_at')
            ->limit(4)
            ->get();

        foreach ($recentEmployees as $emp) {
            $activities->push([
                'id' => 'emp-'.$emp->id,
                'type' => 'employee',
                'title' => $emp->full_name,
                'subtitle' => 'Joined company · '.$emp->employee_number,
                'timestamp' => $emp->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'icon' => 'tabler-user-plus',
                'color' => 'primary',
                'route' => ['name' => 'people-skills-employees-list'],
            ]);
        }

        // Recent Leave Requests
        $recentLeaves = LeaveRequest::with(['employee', 'leaveType'])
            ->latest('created_at')
            ->limit(4)
            ->get();

        foreach ($recentLeaves as $leave) {
            $activities->push([
                'id' => 'leave-'.$leave->id,
                'type' => 'leave',
                'title' => ($leave->employee?->full_name ?? 'Employee').' — '.$leave->leaveType?->name,
                'subtitle' => 'Leave requested ('.ucfirst($leave->status).')',
                'timestamp' => $leave->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'icon' => 'tabler-calendar-time',
                'color' => $leave->status === 'approved' ? 'success' : ($leave->status === 'submitted' ? 'warning' : 'info'),
                'route' => ['name' => 'people-skills-time-off-list', 'query' => ['status' => $leave->status]],
            ]);
        }

        // Recent Vehicle Activities
        $recentVehicleEvents = VehicleActivityLog::with('vehicle')
            ->latest('event_date')
            ->limit(4)
            ->get();

        foreach ($recentVehicleEvents as $log) {
            $activities->push([
                'id' => 'veh-'.$log->id,
                'type' => 'fleet',
                'title' => ($log->vehicle?->plate_number ?? 'Vehicle').' · '.$log->event_title,
                'subtitle' => $log->event_description ?: ucfirst(str_replace('_', ' ', (string) $log->event_type)),
                'timestamp' => $log->event_date?->toIso8601String() ?? now()->toIso8601String(),
                'icon' => 'tabler-car',
                'color' => 'info',
                'route' => ['name' => 'people-skills-vehicles-list'],
            ]);
        }

        // Sort combined feed descending by timestamp and take top 8
        return $activities
            ->sortByDesc('timestamp')
            ->values()
            ->take(8)
            ->all();
    }
}
