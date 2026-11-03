<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Policies;

use App\Models\User;
use App\Modules\Logistics\Models\ReturnOrder;

class ReturnOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('read-Logistics')
            || $user->can('read-delivery_work_orders')
            || $user->hasRole('admin');
    }

    public function view(User $user, ?ReturnOrder $returnOrder = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create-Logistics')
            || $user->can('create-delivery_work_orders')
            || $user->hasRole('admin');
    }

    public function update(User $user, ?ReturnOrder $returnOrder = null): bool
    {
        return $user->can('edit-Logistics')
            || $user->can('edit-delivery_work_orders')
            || $user->hasRole('admin');
    }

    public function delete(User $user, ?ReturnOrder $returnOrder = null): bool
    {
        return $user->can('delete-Logistics')
            || $user->can('delete-delivery_work_orders')
            || $user->hasRole('admin');
    }

    public function validate(User $user, ?ReturnOrder $returnOrder = null): bool
    {
        return $this->update($user, $returnOrder);
    }
}
