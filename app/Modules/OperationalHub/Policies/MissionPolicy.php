<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Policies;

use App\Models\User;
use App\Modules\OperationalHub\Models\Mission;

class MissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read-OperationalHub')
            || $user->can('read-missions')
            || $user->hasRole('admin');
    }

    public function view(User $user, ?Mission $mission = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-OperationalHub')
            || $user->can('create-missions')
            || $user->hasRole('admin');
    }

    public function update(User $user, ?Mission $mission = null): bool
    {
        return $user->can('edit-OperationalHub')
            || $user->can('edit-missions')
            || $user->hasRole('admin');
    }

    public function delete(User $user, ?Mission $mission = null): bool
    {
        return $user->can('delete-OperationalHub')
            || $user->can('delete-missions')
            || $user->hasRole('admin');
    }

    public function execute(User $user, ?Mission $mission = null): bool
    {
        return $this->update($user, $mission);
    }
}
