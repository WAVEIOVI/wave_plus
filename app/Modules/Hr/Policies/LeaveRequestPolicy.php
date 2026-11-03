<?php

declare(strict_types=1);

namespace App\Modules\Hr\Policies;

use App\Models\User;
use App\Modules\Hr\Models\LeaveRequest;

class LeaveRequestPolicy
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
        return $user->can('hr.leave.view')
            || $user->can('read-PeopleSkills')
            || $user->hasRole(['operations_responsible', 'Staff', 'Responsable']);
    }

    public function view(User $user, LeaveRequest $request): bool
    {
        return $user->can('hr.leave.view')
            || $user->can('read-PeopleSkills')
            || $request->employee?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('hr.leave.request') || $user->can('read-PeopleSkills');
    }

    public function approve(User $user, LeaveRequest $request): bool
    {
        return $user->can('hr.leave.approve') || $user->hasRole(['Super Admin', 'Admin', 'Responsable']);
    }

    /**
     * Submit a draft request for approval: the owner (or an HR approver) only.
     */
    public function submit(User $user, LeaveRequest $request): bool
    {
        return $user->can('hr.leave.request')
            || $user->can('read-PeopleSkills')
            || $user->can('hr.leave.approve')
            || $request->employee?->user_id === $user->id
            || $request->requested_by_id === $user->id;
    }

    /**
     * Cancel a request: the owner can cancel draft/submitted requests,
     * only approvers can cancel approved ones.
     */
    public function cancel(User $user, LeaveRequest $request): bool
    {
        $ownsRequest = $request->employee?->user_id === $user->id
            || $request->requested_by_id === $user->id;

        if ($request->status === 'approved') {
            return $user->can('hr.leave.approve')
                || $user->hasRole(['Super Admin', 'Admin', 'Responsable']);
        }

        return $ownsRequest || $user->can('hr.leave.approve');
    }
}
