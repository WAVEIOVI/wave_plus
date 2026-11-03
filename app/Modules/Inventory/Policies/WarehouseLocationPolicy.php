<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

class WarehouseLocationPolicy extends InventoryPolicy
{
    protected function subject(): string
    {
        return 'inventory-locations';
    }
}
