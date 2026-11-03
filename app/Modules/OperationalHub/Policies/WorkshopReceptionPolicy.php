<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Policies;

use App\Models\User;
use App\Modules\OperationalHub\Models\WorkshopReception;

class WorkshopReceptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read-Workshop')
            || $user->can('read-workshop_receptions')
            || $user->hasRole('admin');
    }

    public function view(User $user, ?WorkshopReception $reception = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-Workshop')
            || $user->can('create-workshop_receptions')
            || $user->hasRole('admin');
    }

    public function update(User $user, ?WorkshopReception $reception = null): bool
    {
        return $user->can('edit-Workshop')
            || $user->can('edit-workshop_receptions')
            || $user->hasRole('admin');
    }

    public function delete(User $user, ?WorkshopReception $reception = null): bool
    {
        return $user->can('delete-Workshop')
            || $user->can('delete-workshop_receptions')
            || $user->hasRole('admin');
    }
}
