<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Policies;

use App\Models\User;
use App\Modules\OperationalHub\Models\WorkshopUnit;

class WorkshopUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read-Workshop')
            || $user->can('read-workshop_units')
            || $user->hasRole('admin');
    }

    public function view(User $user, ?WorkshopUnit $unit = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-Workshop')
            || $user->can('create-workshop_units')
            || $user->hasRole('admin');
    }

    public function update(User $user, ?WorkshopUnit $unit = null): bool
    {
        return $user->can('edit-Workshop')
            || $user->can('edit-workshop_units')
            || $user->hasRole('admin');
    }

    public function delete(User $user, ?WorkshopUnit $unit = null): bool
    {
        return $user->can('delete-Workshop')
            || $user->can('delete-workshop_units')
            || $user->hasRole('admin');
    }
}
