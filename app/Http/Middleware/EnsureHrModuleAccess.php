<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Hr\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHrModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($this->canAccessHr($request, $user)) {
            return $next($request);
        }

        abort(403, 'You do not have access to People & Skills.');
    }

    private function canAccessHr(Request $request, $user): bool
    {
        if ($user->hasRole(['Super Admin', 'Admin', 'HR Manager', 'HR Officer', 'Attendance Manager', 'Leave Approver', 'Recruiter', 'Fleet Manager', 'Attendance Kiosk', 'kiosk'])) {
            return true;
        }

        // Self-service: every linked employee (or Employee Self-Service role)
        // may access time-clock, their own profile, submit leave requests, view
        // their own balances and attendances, view appraisals and public holidays.
        // Per-endpoint policies restrict them strictly to their own records.
        if (Employee::where('user_id', $user->id)->exists() || $user->hasRole('Employee Self-Service')) {
            if ($request->is([
                'api/hr/time-clock/*',
                'api/hr/leave-requests*',
                'api/hr/leave-balances*',
                'api/hr/attendances*',
                'api/hr/public-holidays*',
                'api/hr/appraisals*',
            ])) {
                return true;
            }

            if ($request->is('api/hr/employees/*') && $request->isMethod('GET')) {
                return true;
            }
        }

        if ($user->can('read-PeopleSkills') || $user->can('read-hr-dashboard') || $user->can('hr.attendance.kiosk')) {
            return true;
        }

        foreach ([
            'hr.employees.view',
            'hr.employees.create',
            'hr.employees.edit',
            'hr.employees.archive',
            'hr.employees.view-sensitive',
            'hr.departments.view',
            'hr.departments.manage',
            'hr.positions.view',
            'hr.positions.manage',
            'hr.teams.view',
            'hr.teams.manage',
            'hr.attendance.view',
            'hr.attendance.manage',
            'hr.attendance.correct',
            'hr.attendance.kiosk',
            'hr.leave.view',
            'hr.leave.request',
            'hr.leave.approve',
            'hr.leave.manage',
            'hr.recruitment.view',
            'hr.recruitment.manage',
            'hr.recruitment.hire',
            'hr.training.view',
            'hr.training.manage',
            'hr.certifications.view',
            'hr.certifications.manage',
            'hr.skills.view',
            'hr.skills.manage',
            'hr.workforce.view',
            'hr.workforce.assign',
            'hr.appraisals.view',
            'hr.appraisals.manage',
            'hr.fleet.view',
            'hr.fleet.manage',
            'hr.fleet.archive',
        ] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
