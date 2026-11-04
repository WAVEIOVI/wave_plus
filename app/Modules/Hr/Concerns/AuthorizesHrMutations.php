<?php

declare(strict_types=1);

namespace App\Modules\Hr\Concerns;

use App\Modules\Hr\Models\Employee;
use Illuminate\Http\Request;

trait AuthorizesHrMutations
{
    protected function authorizeHrRead(string $permission): void
    {
        $user = request()->user();
        abort_unless($user, 403);

        if ($user->hasRole(['Super Admin', 'Admin', 'HR Manager'])) {
            return;
        }

        $managePermission = str_replace('.view', '.manage', $permission);
        $archivePermission = str_replace('.view', '.archive', $permission);

        abort_unless(
            $user->can($permission)
                || $user->can($managePermission)
                || $user->can($archivePermission)
                || $user->can('read-PeopleSkills'),
            403,
            'You do not have permission to view HR records.'
        );
    }

    protected function authorizeHrMutation(?string $permission = null): void
    {
        $user = request()->user();
        abort_unless($user, 403);

        if ($user->hasRole(['Super Admin', 'Admin', 'HR Manager'])) {
            return;
        }

        abort_unless($permission && $user->can($permission), 403, 'You do not have permission to change HR records.');
    }

    protected function resolveClockEmployee(Request $request)
    {
        $user = $request->user();
        $requestedId = $request->input('employee_id');

        if ($requestedId) {
            $canClockOthers = $user->hasRole(['Super Admin', 'Admin'])
                || $user->can('hr.attendance.manage');

            abort_unless($canClockOthers, 403, 'You can only clock in or out for your own employee record.');

            return \App\Modules\Hr\Models\Employee::findOrFail($requestedId);
        }

        $employee = \App\Modules\Hr\Models\Employee::where('user_id', $user->id)->first();
        abort_unless($employee, 422, 'No linked employee record found for the current user.');

        return $employee;
    }

    protected function resolveClockEmployeeForStatus(Request $request): ?Employee
    {
        $user = $request->user();
        $requestedId = $request->input('employee_id');

        if ($requestedId) {
            $canViewOthers = $user->hasRole(['Super Admin', 'Admin'])
                || $user->can('hr.attendance.view')
                || $user->can('hr.attendance.manage');

            abort_unless($canViewOthers, 403, 'You can only view your own time clock status.');

            return Employee::find($requestedId);
        }

        return Employee::where('user_id', $user->id)->first();
    }
}
