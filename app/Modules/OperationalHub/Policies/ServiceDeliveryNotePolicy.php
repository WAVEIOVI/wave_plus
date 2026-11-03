<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Policies;

use App\Models\User;
use App\Modules\OperationalHub\Models\ServiceDeliveryNote;

class ServiceDeliveryNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read-OperationalHub')
            || $user->can('read-service_delivery_notes')
            || $user->hasRole('admin');
    }

    public function view(User $user, ?ServiceDeliveryNote $sdn = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-OperationalHub')
            || $user->can('create-service_delivery_notes')
            || $user->hasRole('admin');
    }

    public function update(User $user, ?ServiceDeliveryNote $sdn = null): bool
    {
        return $user->can('edit-OperationalHub')
            || $user->can('edit-service_delivery_notes')
            || $user->hasRole('admin');
    }

    public function delete(User $user, ?ServiceDeliveryNote $sdn = null): bool
    {
        return $user->can('delete-OperationalHub')
            || $user->can('delete-service_delivery_notes')
            || $user->hasRole('admin');
    }
}
