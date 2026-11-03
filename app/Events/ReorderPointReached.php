<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Inventory\Models\StockItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReorderPointReached
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public StockItem $stockItem,
        public CatalogItem $catalogItem
    ) {}
}
