<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Policies;

use App\Models\User;
use App\Modules\Logistics\Models\DeliveryWave;

class DeliveryWavePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read-Logistics')
            || $user->can('read-delivery_waves')
            || $user->hasRole('admin');
    }

    public function view(User $user, ?DeliveryWave $wave = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-Logistics')
            || $user->can('create-delivery_waves')
            || $user->hasRole('admin');
    }

    public function update(User $user, ?DeliveryWave $wave = null): bool
    {
        return $user->can('edit-Logistics')
            || $user->can('edit-delivery_waves')
            || $user->hasRole('admin');
    }

    public function delete(User $user, ?DeliveryWave $wave = null): bool
    {
        return $user->can('delete-Logistics')
            || $user->can('delete-delivery_waves')
            || $user->hasRole('admin');
    }

    public function dispatch(User $user, ?DeliveryWave $wave = null): bool
    {
        return $this->update($user, $wave);
    }

    public function complete(User $user, ?DeliveryWave $wave = null): bool
    {
        return $this->update($user, $wave);
    }
}
