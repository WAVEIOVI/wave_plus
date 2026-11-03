<?php

declare(strict_types=1);

namespace App\Modules\Hr\Policies;

use App\Models\User;

class RecruitmentPolicy
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
        return $user->can('hr.recruitment.view')
            || $user->can('read-PeopleSkills')
            || $user->hasRole(['Responsable', 'Staff']);
    }

    public function manage(User $user): bool
    {
        return $user->can('hr.recruitment.manage');
    }

    public function hire(User $user): bool
    {
        return $user->can('hr.recruitment.hire') || $user->hasRole(['Super Admin', 'Admin']);
    }
}
