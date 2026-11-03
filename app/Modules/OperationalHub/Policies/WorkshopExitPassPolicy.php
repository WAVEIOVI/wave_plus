<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Policies;

use App\Models\User;
use App\Modules\OperationalHub\Models\WorkshopExitPass;

class WorkshopExitPassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read-OperationalHub')
            || $user->can('read-Workshop')
            || $user->can('read-workshop_exit_passes')
            || $user->hasRole('admin');
    }

    public function view(User $user, ?WorkshopExitPass $pass = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-OperationalHub')
            || $user->can('create-Workshop')
            || $user->can('create-workshop_exit_passes')
            || $user->hasRole('admin');
    }

    public function update(User $user, ?WorkshopExitPass $pass = null): bool
    {
        return $user->can('edit-OperationalHub')
            || $user->can('edit-Workshop')
            || $user->can('edit-workshop_exit_passes')
            || $user->hasRole('admin');
    }

    public function delete(User $user, ?WorkshopExitPass $pass = null): bool
    {
        return $user->can('delete-OperationalHub')
            || $user->can('delete-Workshop')
            || $user->can('delete-workshop_exit_passes')
            || $user->hasRole('admin');
    }
}
