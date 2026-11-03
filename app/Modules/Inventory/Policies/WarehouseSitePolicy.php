<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

class WarehouseSitePolicy extends InventoryPolicy
{
    protected function subject(): string
    {
        return 'inventory-sites';
    }
}
