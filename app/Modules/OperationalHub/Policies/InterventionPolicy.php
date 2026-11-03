<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Policies;

use App\Models\User;
use App\Modules\OperationalHub\Models\Intervention;

class InterventionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read-OperationalHub')
            || $user->can('read-interventions')
            || $user->hasRole('admin');
    }

    public function view(User $user, ?Intervention $intervention = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-OperationalHub')
            || $user->can('create-interventions')
            || $user->hasRole('admin');
    }

    public function update(User $user, ?Intervention $intervention = null): bool
    {
        return $user->can('edit-OperationalHub')
            || $user->can('edit-interventions')
            || $user->hasRole('admin');
    }

    public function delete(User $user, ?Intervention $intervention = null): bool
    {
        return $user->can('delete-OperationalHub')
            || $user->can('delete-interventions')
            || $user->hasRole('admin');
    }
}
