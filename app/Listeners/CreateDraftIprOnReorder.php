<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ReorderPointReached;
use App\Modules\Procurement\Models\InternalPurchaseRequest;

class CreateDraftIprOnReorder
{
    public function handle(ReorderPointReached $event): void
    {
        $ipr = InternalPurchaseRequest::create([
            'status' => 'draft',
            'source' => 'auto_reorder',
        ]);

        $qty = $event->catalogItem->reorder_qty ?? ($event->catalogItem->reorder_point * 2);

        $ipr->lines()->create([
            'catalog_item_id' => $event->catalogItem->id,
            'qty' => $qty,
        ]);
    }
}
