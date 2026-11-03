<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StockMovementPolicy extends InventoryPolicy
{
    protected function subject(): string
    {
        return 'inventory-movements';
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }
}
