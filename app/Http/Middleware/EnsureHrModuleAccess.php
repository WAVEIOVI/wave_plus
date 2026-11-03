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
        // Self-service time clock: every linked employee may punch their own
        // badge; per-endpoint checks below restrict them to their own record.
        if ($request->is('api/hr/time-clock/*')
            && Employee::where('user_id', $user->id)->exists()) {
            return true;
        }
        if ($user->hasRole(['Super Admin', 'Admin', 'Attendance Kiosk', 'kiosk'])) {
            return true;
        }

        if ($user->can('read-PeopleSkills') || $user->can('hr.attendance.kiosk')) {
            return true;
        }

        foreach ([
            'hr.employees.view',
            'hr.attendance.view',
            'hr.leave.view',
            'hr.recruitment.view',
            'hr.training.view',
            'hr.certifications.view',
            'hr.workforce.view',
            // Mutation grants also imply module access; per-endpoint policies
            // enforce the exact create/edit/archive/approve rights.
            'hr.employees.create',
            'hr.employees.edit',
            'hr.employees.archive',
            'hr.attendance.manage',
            'hr.attendance.correct',
            'hr.leave.request',
            'hr.leave.approve',
            'hr.leave.manage',
            'hr.recruitment.manage',
            'hr.recruitment.hire',
            'hr.training.manage',
            'hr.certifications.manage',
            'hr.workforce.assign',
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
