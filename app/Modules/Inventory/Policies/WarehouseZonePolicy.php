<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

class WarehouseZonePolicy extends InventoryPolicy
{
    protected function subject(): string
    {
        return 'inventory-zones';
    }
}
