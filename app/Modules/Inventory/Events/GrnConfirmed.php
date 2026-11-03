<?php

namespace App\Modules\Inventory\Events;

use App\Modules\Procurement\Models\GoodsReceiptNote;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Goods Receipt Note is confirmed (status → 'confirmed' or 'stocked').
 *
 * Listeners:
 *  - StockMovementService::handleGrnConfirmed()
 *    Creates GRN_RECEIPT stock movements and updates stock_items.
 */
class GrnConfirmed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly GoodsReceiptNote $grn,
        public readonly string $performedById,
    ) {}
}
