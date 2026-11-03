<?php

declare(strict_types=1);

namespace App\Modules\Hr\Policies;

use App\Models\User;
use App\Modules\Hr\Models\Attendance;

class AttendancePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('hr.attendance.view')
            || $user->can('read-PeopleSkills')
            || $user->hasRole(['operations_responsible', 'Staff', 'Responsable']);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->can('hr.attendance.view')
            || $user->can('read-PeopleSkills')
            || $attendance->employee?->user_id === $user->id;
    }

    public function clock(User $user): bool
    {
        return true; // Any authenticated user linked to employee or authorized
    }

    /**
     * Create / update / delete attendance CRUD (manual roster management).
     */
    public function manage(User $user): bool
    {
        return $user->can('hr.attendance.manage');
    }

    public function correct(User $user): bool
    {
        return $user->can('hr.attendance.correct')
            || $user->can('hr.attendance.manage')
            || $user->can('read-PeopleSkills');
    }
}
