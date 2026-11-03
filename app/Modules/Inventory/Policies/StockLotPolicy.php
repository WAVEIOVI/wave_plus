<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Policies;

class StockLotPolicy extends InventoryPolicy
{
    protected function subject(): string
    {
        return 'inventory-lots';
    }
}
