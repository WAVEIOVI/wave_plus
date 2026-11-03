<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Models\SalesOrderLine;
use App\Modules\Inventory\Events\GrnConfirmed;
use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Procurement\Models\GoodsReceiptNote;
use App\Modules\Procurement\Models\GrnLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Core inventory service. Quantities change only through stock_movements.
 * stock_items is a locked current-state cache of that ledger.
 */
class StockMovementService
{
    public function __construct(
        private readonly StockBalanceService $balances,
    ) {}

    public function handleGrnConfirmed(GrnConfirmed $event): void
    {
        $performedBy = $event->performedById;

        DB::transaction(function () use ($event, $performedBy) {
            $grn = GoodsReceiptNote::query()->lockForUpdate()->findOrFail($event->grn->id);
            $grn->load('lines.catalogItem', 'lines.poLine', 'purchaseOrder');

            if ($grn->status === 'stocked') {
                return;
            }

            $grn->lines
                ->filter(fn (GrnLine $line) => $line->qty_accepted > 0)
                ->each(function (GrnLine $line) use ($grn, $performedBy) {
                    $this->processGrnLine($grn, $line, $performedBy);
                });

            $grn->update(['status' => 'stocked']);

            Log::info('[Inventory] GRN stocked', [
                'grn_id' => $grn->id,
                'reference' => $grn->reference,
            ]);
        });
    }

    private function processGrnLine(GoodsReceiptNote $grn, GrnLine $line, string $performedBy): void
    {
        $alreadyPosted = StockMovement::query()
            ->where('movement_type', 'GRN_RECEIPT')
            ->where('source_document_id', $grn->id)
            ->where('source_line_id', $line->id)
            ->exists();

        if ($alreadyPosted) {
            return;
        }

        if (! $line->catalogItem?->is_stockable) {
            Log::warning('[Inventory] Skipping non-stockable item', [
                'catalog_item_id' => $line->catalog_item_id,
            ]);

            return;
        }

        if (! $line->storage_location_id) {
            abort(422, "Cannot stock GRN line {$line->id}: storage location is required.");
        }

        $this->balances->assertUsableLocation($line->storage_location_id);

        $currency = $grn->purchaseOrder->currency ?? 'TND';
        $variantId = $line->catalog_variant_id ?? $line->poLine?->variant_id;

        if (! $variantId) {
            $defaultVariant = \App\Modules\Catalog\Models\CatalogVariant::where('catalog_item_id', $line->catalog_item_id)
                ->where('is_default', true)
                ->value('id')
                ?? \App\Modules\Catalog\Models\CatalogVariant::where('catalog_item_id', $line->catalog_item_id)->value('id');

            if (! $defaultVariant) {
                abort(422, "Cannot stock GRN line: catalog item {$line->catalog_item_id} has no variants.");
            }

            $variantId = $defaultVariant;
        }

        $stockLotId = null;
        if ($line->lot_number) {
            $stockLot = StockLot::firstOrCreate(
                [
                    'catalog_item_id' => $line->catalog_item_id,
                    'lot_number' => $line->lot_number,
                ],
                [
                    'supplier_id' => $grn->supplier_id,
                    'expiry_date' => $line->expiry_date,
                    'status' => 'active',
                    'created_by' => $performedBy,
                ]
            );
            $stockLotId = $stockLot->id;
        }

        StockMovement::create([
            'reference' => $this->generateMovementReference(),
            'movement_type' => 'GRN_RECEIPT',
            'source_document_type' => GoodsReceiptNote::class,
            'source_document_id' => $grn->id,
            'source_line_id' => $line->id,
            'catalog_item_id' => $line->catalog_item_id,
            'catalog_variant_id' => $variantId,
            'stock_lot_id' => $stockLotId,
            'quantity' => $line->qty_accepted,
            'uom_id' => $line->catalogItem->uom_id,
            'from_location_id' => null,
            'to_location_id' => $line->storage_location_id,
            'unit_cost' => $line->unit_cost_ht,
            'total_cost' => (float) bcmul((string) $line->qty_accepted, (string) $line->unit_cost_ht, 3),
            'currency' => $currency,
            'reason' => "GRN receipt: {$grn->reference}",
            'metadata' => [
                'po_id' => $grn->po_id,
                'po_line_id' => $line->po_line_id,
                'qc_result' => $line->qc_result,
            ],
            'performed_by' => $performedBy,
            'performed_at' => now(),
        ]);

        $stockItem = $this->balances->lockOrCreate(
            $line->catalog_item_id,
            $variantId,
            $line->storage_location_id,
            $stockLotId,
            [
                'unit_cost' => $line->unit_cost_ht,
                'currency' => $currency,
                'condition' => 'new',
            ]
        );

        $this->balances->incrementOnHand($stockItem, (string) $line->qty_accepted);
        $stockItem->unit_cost = (float) $line->unit_cost_ht;
        $stockItem->save();
    }

    public function adjustStock(
        string $catalogItemId,
        string $catalogVariantId,
        string $locationId,
        string $quantity,
        bool $isPlus,
        string $reason,
        string $performedBy,
        ?string $stockLotId = null,
        ?string $unitCost = null,
        string $condition = 'new',
        string $currency = 'TND',
        string $movementType = '',
    ): StockMovement {
        return DB::transaction(function () use (
            $catalogItemId,
            $catalogVariantId,
            $locationId,
            $quantity,
            $isPlus,
            $reason,
            $performedBy,
            $stockLotId,
            $unitCost,
            $condition,
            $currency,
            $movementType
        ) {
            $this->assertStockable($catalogItemId);
            $this->balances->assertUsableLocation($locationId);

            $type = $movementType !== ''
                ? $movementType
                : ($isPlus ? 'ADJUSTMENT_PLUS' : 'ADJUSTMENT_MINUS');
            $signedQty = $isPlus ? $quantity : bcmul('-1', $quantity, 4);
            $catalogItem = CatalogItem::findOrFail($catalogItemId);

            $movement = StockMovement::create([
                'reference' => $this->generateMovementReference(),
                'movement_type' => $type,
                'catalog_item_id' => $catalogItemId,
                'catalog_variant_id' => $catalogVariantId,
                'stock_lot_id' => $stockLotId,
                'quantity' => (float) $signedQty,
                'uom_id' => $catalogItem->uom_id,
                'from_location_id' => $isPlus ? null : $locationId,
                'to_location_id' => $isPlus ? $locationId : null,
                'unit_cost' => $unitCost ? (float) $unitCost : null,
                'total_cost' => $unitCost ? (float) bcmul($quantity, $unitCost, 3) : null,
                'currency' => $currency,
                'reason' => $reason,
                'performed_by' => $performedBy,
                'performed_at' => now(),
            ]);

            $stockItem = $this->balances->lockOrCreate(
                $catalogItemId,
                $catalogVariantId,
                $locationId,
                $stockLotId,
                [
                    'unit_cost' => $unitCost ? (float) $unitCost : null,
                    'currency' => $currency,
                    'condition' => $condition,
                ]
            );

            if ($isPlus) {
                $this->balances->incrementOnHand($stockItem, (string) $quantity);
            } else {
                $this->balances->decrementOnHand($stockItem, (string) $quantity);
            }

            if ($unitCost) {
                $stockItem->unit_cost = (float) $unitCost;
                $stockItem->save();
            }

            return $movement;
        });
    }

    public function transferStock(
        string $catalogItemId,
        string $catalogVariantId,
        string $fromLocationId,
        string $toLocationId,
        string $quantity,
        string $performedBy,
        ?string $stockLotId = null,
        ?string $reason = null,
        ?string $condition = null,
    ): array {
        return DB::transaction(function () use (
            $catalogItemId,
            $catalogVariantId,
            $fromLocationId,
            $toLocationId,
            $quantity,
            $performedBy,
            $stockLotId,
            $reason,
            $condition
        ) {
            $this->assertStockable($catalogItemId);
            if ($fromLocationId === $toLocationId) {
                abort(422, 'Source and destination locations must be different.');
            }
            $this->balances->assertUsableLocation($fromLocationId);
            $this->balances->assertUsableLocation($toLocationId);

            $sourceItem = $this->balances->lockOrCreate(
                $catalogItemId,
                $catalogVariantId,
                $fromLocationId,
                $stockLotId
            );

            $unitCost = $sourceItem->unit_cost;
            $currency = $sourceItem->currency ?? 'TND';
            $catalogItem = CatalogItem::findOrFail($catalogItemId);

            $outMovement = StockMovement::create([
                'reference' => $this->generateMovementReference(),
                'movement_type' => 'TRANSFER_OUT',
                'catalog_item_id' => $catalogItemId,
                'catalog_variant_id' => $catalogVariantId,
                'stock_lot_id' => $stockLotId,
                'quantity' => (float) bcmul('-1', $quantity, 4),
                'uom_id' => $catalogItem->uom_id,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost ? (float) bcmul($quantity, (string) $unitCost, 3) : null,
                'currency' => $currency,
                'reason' => $reason ?? 'Stock transfer',
                'performed_by' => $performedBy,
                'performed_at' => now(),
            ]);

            $this->balances->decrementOnHand($sourceItem, (string) $quantity);

            $inMovement = StockMovement::create([
                'reference' => $this->generateMovementReference(),
                'movement_type' => 'TRANSFER_IN',
                'catalog_item_id' => $catalogItemId,
                'catalog_variant_id' => $catalogVariantId,
                'stock_lot_id' => $stockLotId,
                'quantity' => (float) $quantity,
                'uom_id' => $catalogItem->uom_id,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost ? (float) bcmul($quantity, (string) $unitCost, 3) : null,
                'currency' => $currency,
                'reason' => $reason ?? 'Stock transfer',
                'performed_by' => $performedBy,
                'performed_at' => now(),
            ]);

            $destItem = $this->balances->lockOrCreate(
                $catalogItemId,
                $catalogVariantId,
                $toLocationId,
                $stockLotId,
                [
                    'unit_cost' => $unitCost,
                    'currency' => $currency,
                    'condition' => $condition ?? $sourceItem->condition ?? 'new',
                ]
            );

            $this->balances->incrementOnHand($destItem, (string) $quantity);
            $destItem->unit_cost = (float) $unitCost;
            $destItem->save();

            return [$outMovement, $inMovement];
        });
    }

    public function initialLoad(
        string $catalogItemId,
        string $catalogVariantId,
        string $locationId,
        string $qty,
        string $unitCost,
        string $currency,
        string $performedBy,
        ?string $stockLotId = null,
        string $condition = 'new'
    ): StockMovement {
        return DB::transaction(function () use (
            $catalogItemId,
            $catalogVariantId,
            $locationId,
            $qty,
            $unitCost,
            $currency,
            $performedBy,
            $stockLotId,
            $condition
        ) {
            $this->assertStockable($catalogItemId);
            $this->balances->assertUsableLocation($locationId);
            $catalogItem = CatalogItem::findOrFail($catalogItemId);

            $movement = StockMovement::create([
                'reference' => $this->generateMovementReference(),
                'movement_type' => 'INITIAL_LOAD',
                'catalog_item_id' => $catalogItemId,
                'catalog_variant_id' => $catalogVariantId,
                'stock_lot_id' => $stockLotId,
                'quantity' => (float) $qty,
                'uom_id' => $catalogItem->uom_id,
                'from_location_id' => null,
                'to_location_id' => $locationId,
                'unit_cost' => (float) $unitCost,
                'total_cost' => (float) bcmul($qty, $unitCost, 3),
                'currency' => $currency,
                'reason' => 'Initial stock load',
                'performed_by' => $performedBy,
                'performed_at' => now(),
            ]);

            $stockItem = $this->balances->lockOrCreate(
                $catalogItemId,
                $catalogVariantId,
                $locationId,
                $stockLotId,
                [
                    'unit_cost' => (float) $unitCost,
                    'currency' => $currency,
                    'condition' => $condition,
                ]
            );

            $this->balances->incrementOnHand($stockItem, $qty);
            $stockItem->unit_cost = (float) $unitCost;
            $stockItem->save();

            return $movement;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, StockMovement>
     */
    public function initialLoadBatch(array $items, string $performedBy): array
    {
        return DB::transaction(function () use ($items, $performedBy) {
            $movements = [];
            foreach ($items as $item) {
                $movements[] = $this->initialLoad(
                    catalogItemId: $item['catalog_item_id'],
                    catalogVariantId: $item['catalog_variant_id'],
                    locationId: $item['location_id'],
                    qty: (string) $item['quantity'],
                    unitCost: (string) $item['unit_cost'],
                    currency: $item['currency'] ?? 'TND',
                    performedBy: $performedBy,
                    stockLotId: $item['stock_lot_id'] ?? null,
                    condition: $item['condition'] ?? 'new'
                );
            }

            return $movements;
        });
    }

    /**
     * @deprecated SO acceptance must not reserve stock. Reservations belong to DWO allocation.
     * Kept for historical inventory tests that still exercise this path.
     */
    public function reserveForSalesOrder(SalesOrder $order, string $performedBy): void
    {
        DB::transaction(function () use ($order, $performedBy) {
            foreach ($order->lines as $line) {
                if ($line->is_ad_hoc || ! $line->catalog_item_id) {
                    continue;
                }

                $catalogItem = $line->catalogItem;
                if (! $catalogItem || ! $catalogItem->is_stockable) {
                    continue;
                }

                $alreadyReserved = (float) StockReservation::where('reservable_type', SalesOrder::class)
                    ->where('reservable_id', $order->id)
                    ->where('catalog_item_id', $line->catalog_item_id)
                    ->where('status', 'active')
                    ->sum('qty_reserved');

                $qtyNeeded = (float) $line->qty - $alreadyReserved;
                if ($qtyNeeded <= 0) {
                    $line->update(['reserved_qty' => $alreadyReserved]);

                    continue;
                }

                $stockItems = StockItem::where('catalog_item_id', $line->catalog_item_id)
                    ->when($line->variant_id, fn ($q) => $q->where('catalog_variant_id', $line->variant_id))
                    ->where('status', 'active')
                    ->where('qty_available', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                $newlyReserved = 0.0;
                foreach ($stockItems as $stockItem) {
                    if ($qtyNeeded <= 0) {
                        break;
                    }

                    $available = (float) $stockItem->qty_available;
                    $reserveQty = min($qtyNeeded, $available);

                    StockReservation::create([
                        'stock_item_id' => $stockItem->id,
                        'catalog_item_id' => $catalogItem->id,
                        'reservable_type' => SalesOrder::class,
                        'reservable_id' => $order->id,
                        'qty_reserved' => $reserveQty,
                        'status' => 'active',
                        'reserved_at' => now(),
                        'expires_at' => $order->requested_delivery_date ? $order->requested_delivery_date->copy()->addDays(7) : now()->addDays(14),
                        'reserved_by' => $performedBy,
                    ]);

                    $stockItem->qty_reserved = (float) bcadd((string) $stockItem->qty_reserved, (string) $reserveQty, 4);
                    $stockItem->recalculateAvailable();
                    $this->balances->assertNonNegative($stockItem);
                    $stockItem->save();

                    $qtyNeeded -= $reserveQty;
                    $newlyReserved += $reserveQty;
                }

                $line->update(['reserved_qty' => $alreadyReserved + $newlyReserved]);
            }
        });
    }

    public function dispatchStock(SalesOrderLine $line, string $performedBy): array
    {
        return DB::transaction(function () use ($line, $performedBy) {
            if ($line->is_ad_hoc || ! $line->catalog_item_id) {
                abort(422, 'Cannot dispatch ad-hoc or non-catalog lines.');
            }

            $alreadyDispatched = (float) StockMovement::query()
                ->where('movement_type', 'SALES_DISPATCH')
                ->where('source_line_id', $line->id)
                ->selectRaw('SUM(ABS(quantity)) as qty')
                ->value('qty');

            $order = $line->salesOrder;
            $qtyRemaining = (float) $line->qty - $alreadyDispatched;
            if ($qtyRemaining <= 0) {
                $line->fulfillment_status = 'fulfilled';
                $line->save();

                return [];
            }

            $reservations = StockReservation::where('reservable_type', SalesOrder::class)
                ->where('reservable_id', $order->id)
                ->where('catalog_item_id', $line->catalog_item_id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            $movements = [];

            foreach ($reservations as $res) {
                if ($qtyRemaining <= 0) {
                    break;
                }

                $dispatchQty = min($qtyRemaining, (float) $res->qty_reserved);
                $stockItem = StockItem::query()->lockForUpdate()->findOrFail($res->stock_item_id);

                $movements[] = $this->writeDispatchMovement($line, $order, $stockItem, $dispatchQty, $performedBy, false);

                $stockItem->qty_on_hand = (float) bcsub((string) $stockItem->qty_on_hand, (string) $dispatchQty, 4);
                $stockItem->qty_reserved = (float) bcsub((string) $stockItem->qty_reserved, (string) $dispatchQty, 4);
                $stockItem->recalculateAvailable();
                $this->balances->assertNonNegative($stockItem);
                $stockItem->save();

                if ((float) $res->qty_reserved <= $dispatchQty) {
                    $res->markFulfilled();
                } else {
                    $res->qty_reserved = (float) bcsub((string) $res->qty_reserved, (string) $dispatchQty, 4);
                    $res->save();
                }

                $qtyRemaining -= $dispatchQty;
            }

            if ($qtyRemaining > 0) {
                $stockItems = StockItem::where('catalog_item_id', $line->catalog_item_id)
                    ->where('status', 'active')
                    ->where('qty_available', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->get();

                foreach ($stockItems as $stockItem) {
                    if ($qtyRemaining <= 0) {
                        break;
                    }

                    $available = (float) $stockItem->qty_available;
                    if ($available <= 0) {
                        continue;
                    }

                    $dispatchQty = min($qtyRemaining, $available);
                    $movements[] = $this->writeDispatchMovement($line, $order, $stockItem, $dispatchQty, $performedBy, true);
                    $this->balances->decrementOnHand($stockItem, (string) $dispatchQty);
                    $qtyRemaining -= $dispatchQty;
                }
            }

            if ($qtyRemaining > 0.0001) {
                abort(422, "Cannot complete sales dispatch: shortfall of {$qtyRemaining} for line {$line->id}.");
            }

            $line->fulfillment_status = 'fulfilled';
            $line->save();

            return $movements;
        });
    }

    private function writeDispatchMovement(
        SalesOrderLine $line,
        SalesOrder $order,
        StockItem $stockItem,
        float $dispatchQty,
        string $performedBy,
        bool $unreserved
    ): StockMovement {
        return StockMovement::create([
            'reference' => $this->generateMovementReference(),
            'movement_type' => 'SALES_DISPATCH',
            'source_document_type' => SalesOrder::class,
            'source_document_id' => $order->id,
            'source_line_id' => $line->id,
            'catalog_item_id' => $line->catalog_item_id,
            'catalog_variant_id' => $stockItem->catalog_variant_id,
            'stock_lot_id' => $stockItem->stock_lot_id,
            'quantity' => (float) bcmul((string) $dispatchQty, '-1', 4),
            'uom_id' => $line->catalogItem?->uom_id,
            'from_location_id' => $stockItem->location_id,
            'to_location_id' => null,
            'unit_cost' => $stockItem->unit_cost,
            'total_cost' => $stockItem->unit_cost ? (float) bcmul((string) $dispatchQty, (string) $stockItem->unit_cost, 3) : null,
            'currency' => $order->currency,
            'reason' => $unreserved
                ? "Sales dispatch for SO (Unreserved): {$order->reference}"
                : "Sales dispatch for SO: {$order->reference}",
            'performed_by' => $performedBy,
            'performed_at' => now(),
        ]);
    }

    private function assertStockable(string $catalogItemId): CatalogItem
    {
        $catalogItem = CatalogItem::findOrFail($catalogItemId);
        if (! $catalogItem->is_stockable) {
            abort(422, "Catalog item {$catalogItemId} is not stockable.");
        }

        return $catalogItem;
    }

    private function generateMovementReference(): string
    {
        $year = date('Y');
        $sequenceType = "SM-{$year}";

        $nextValue = (int) DB::transaction(function () use ($sequenceType) {
            $sequence = DB::table('document_sequences')
                ->where('document_type', $sequenceType)
                ->lockForUpdate()
                ->first();

            if ($sequence) {
                $nextValue = (int) $sequence->current_value + 1;
                DB::table('document_sequences')
                    ->where('id', $sequence->id)
                    ->update(['current_value' => $nextValue, 'updated_at' => now()]);

                return $nextValue;
            }

            DB::table('document_sequences')->insert([
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'document_type' => $sequenceType,
                'current_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        });

        return sprintf('%s-%06d', $sequenceType, $nextValue);
    }
}
