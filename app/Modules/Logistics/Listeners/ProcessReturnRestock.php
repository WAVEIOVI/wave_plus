<?php

namespace App\Modules\Logistics\Listeners;

use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Logistics\Events\ReturnValidated;

class ProcessReturnRestock
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {}

    public function handle(ReturnValidated $event): void
    {
        $returnOrder = $event->returnOrder->load('items.dwoItem');

        foreach ($returnOrder->items as $item) {
            $dwoItem = $item->dwoItem;
            if (! $dwoItem || ! $dwoItem->catalog_item_id || ! $item->stock_destination || $item->qty_returned <= 0) {
                continue;
            }

            $variantId = CatalogVariant::where('catalog_item_id', $dwoItem->catalog_item_id)
                ->where('is_default', true)
                ->value('id');

            if (! $variantId) {
                $variantId = CatalogVariant::where('catalog_item_id', $dwoItem->catalog_item_id)
                    ->value('id');
            }

            if (! $variantId) {
                continue;
            }

            $this->stockMovementService->adjustStock(
                catalogItemId: $dwoItem->catalog_item_id,
                catalogVariantId: $variantId,
                locationId: $item->stock_destination,
                quantity: (string) $item->qty_returned,
                isPlus: true,
                reason: "Return restock from {$returnOrder->return_number}",
                performedBy: $event->actor->id,
                condition: $item->condition ?? 'new',
                movementType: 'RETURN_FROM_CUSTOMER',
            );
        }
    }
}
