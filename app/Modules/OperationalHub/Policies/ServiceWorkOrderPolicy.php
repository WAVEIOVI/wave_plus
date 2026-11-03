<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Policies;

use App\Models\User;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;

class ServiceWorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read-OperationalHub')
            || $user->can('read-service_work_orders')
            || $user->hasRole('admin');
    }

    public function view(User $user, ?ServiceWorkOrder $swo = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-OperationalHub')
            || $user->can('create-service_work_orders')
            || $user->hasRole('admin');
    }

    public function update(User $user, ?ServiceWorkOrder $swo = null): bool
    {
        return $user->can('edit-OperationalHub')
            || $user->can('edit-service_work_orders')
            || $user->hasRole('admin');
    }

    public function delete(User $user, ?ServiceWorkOrder $swo = null): bool
    {
        return $user->can('delete-OperationalHub')
            || $user->can('delete-service_work_orders')
            || $user->hasRole('admin');
    }

    public function transition(User $user, ?ServiceWorkOrder $swo = null): bool
    {
        return $this->update($user, $swo);
    }

    public function plan(User $user, ?ServiceWorkOrder $swo = null): bool
    {
        return $this->update($user, $swo);
    }
}
