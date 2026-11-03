<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Policies;

use App\Models\User;
use App\Modules\Logistics\Models\DeliveryWorkOrder;

class DeliveryWorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read-Logistics')
            || $user->can('read-delivery_work_orders')
            || $user->hasRole('admin');
    }

    public function view(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-Logistics')
            || $user->can('create-delivery_work_orders')
            || $user->hasRole('admin');
    }

    public function update(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $user->can('edit-Logistics')
            || $user->can('edit-delivery_work_orders')
            || $user->hasRole('admin');
    }

    public function delete(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $user->can('delete-Logistics')
            || $user->can('delete-delivery_work_orders')
            || $user->hasRole('admin');
    }

    public function transition(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $this->update($user, $dwo);
    }

    public function plan(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $this->update($user, $dwo);
    }

    public function pick(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $this->update($user, $dwo);
    }

    public function load(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $this->update($user, $dwo);
    }

    public function dispatch(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $this->update($user, $dwo);
    }

    public function deliver(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $this->update($user, $dwo);
    }

    public function close(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $this->update($user, $dwo);
    }

    public function cancel(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $this->update($user, $dwo);
    }

    public function createException(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $this->update($user, $dwo);
    }

    public function resolveException(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $this->update($user, $dwo);
    }

    public function recordPod(User $user, ?DeliveryWorkOrder $dwo = null): bool
    {
        return $this->update($user, $dwo);
    }
}
