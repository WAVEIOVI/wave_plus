<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Inventory\Events\GrnConfirmed;
use App\Modules\Inventory\Services\StockMovementService;

/**
 * Listens for GrnConfirmed events and delegates to the
 * StockMovementService to create inventory movements.
 *
 * Register in EventServiceProvider:
 *   GrnConfirmed::class => [ProcessGrnReceipt::class],
 */
class ProcessGrnReceipt
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {}

    public function handle(GrnConfirmed $event): void
    {
        $this->stockMovementService->handleGrnConfirmed($event);
    }
}
