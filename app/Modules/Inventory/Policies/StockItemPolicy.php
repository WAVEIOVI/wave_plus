<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StockItemPolicy extends InventoryPolicy
{
    protected function subject(): string
    {
        return 'inventory-stock-items';
    }

    public function create(User $user): bool
    {
        return false;
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
