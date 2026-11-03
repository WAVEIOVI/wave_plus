<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Services\StockBalanceService;
use App\Modules\Logistics\Models\DeliveryNote;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\DwoLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Posts the physical goods-delivery stock event.
 *
 * Delivery Note confirmation is the only goods-dispatch stock movement.
 * DWO dispatch / loading must not write SALES_DISPATCH movements.
 */
class DeliveryNoteStockService
{
    public function __construct(
        private readonly StockBalanceService $balances,
    ) {}

    /**
     * Confirm a Delivery Note and post SALES_DISPATCH movements (idempotent).
     *
     * This is the authoritative physical delivery event:
     * 1. Validates actual delivered quantity from request (or DWO operational data if not provided)
     * 2. Posts exactly one inventory movement per physical delivery
     * 3. Consumes/reconciles relevant DWO reservations
     * 4. Updates DWO line delivered quantities and item status
     * 5. Updates DWO delivery state
     *
     * CRITICAL: qty_delivered must never exceed actual stock posted. The stock movement
     * quantity MUST match the explicitly provided delivered quantity or be capped by available stock.
     *
     * @param  array<int, array{dwo_line_id: string, qty_delivered: float}>  $deliveredLines
     * @return array<int, StockMovement>
     */
    public function confirmAndPost(DeliveryNote $note, string $performedBy, array $deliveredLines = []): array
    {
        return DB::transaction(function () use ($note, $performedBy, $deliveredLines) {
            $note = DeliveryNote::query()->lockForUpdate()->findOrFail($note->id);

            if ($note->stock_posted_at) {
                return StockMovement::where('movement_type', 'SALES_DISPATCH')
                    ->where('source_document_type', DeliveryNote::class)
                    ->where('source_document_id', $note->id)
                    ->get()
                    ->all();
            }

            $dwo = DeliveryWorkOrder::query()
                ->with(['lines.catalogItem', 'salesOrder'])
                ->findOrFail($note->dwo_id);

            $movements = [];

            // Track delivered quantities per line to update DWO lines after stock posting
            $lineDeliveredQuantities = [];

            // Build a map of delivered quantities from the request
            $deliveredQtyMap = [];
            foreach ($deliveredLines as $dl) {
                $deliveredQtyMap[$dl['dwo_line_id']] = (float) $dl['qty_delivered'];
            }

            foreach ($dwo->lines as $line) {
                $catalogItem = $line->catalogItem;
                if (! $catalogItem || ! $catalogItem->is_stockable) {
                    continue;
                }

                // Use explicitly provided delivered quantity. If not provided, cap at the minimum
                // of allocated/picked/loaded to prevent over-delivery beyond physical reality.
                $explicitQty = $deliveredQtyMap[$line->id] ?? null;

                $physicalUpperBound = (float) min(
                    $line->qty_allocated ?? 0,
                    $line->qty_picked ?? 0,
                    $line->qty_loaded ?? 0
                );

                if ($physicalUpperBound <= 0) {
                    $physicalUpperBound = (float) ($line->qty_allocated ?? 0);
                }

                $qtyToIssue = $explicitQty !== null
                    ? min((float) $explicitQty, $physicalUpperBound)
                    : $physicalUpperBound;

                if ($qtyToIssue <= 0) {
                    continue;
                }

                $alreadyPosted = StockMovement::where('movement_type', 'SALES_DISPATCH')
                    ->where(function ($q) use ($note, $dwo, $line) {
                        $q->where(function ($sub) use ($note, $line) {
                            $sub->where('source_document_type', DeliveryNote::class)
                                ->where('source_document_id', $note->id)
                                ->where('source_line_id', $line->id);
                        })->orWhere(function ($sub) use ($dwo, $line) {
                            $sub->where('source_document_type', DeliveryWorkOrder::class)
                                ->where('source_document_id', $dwo->id)
                                ->where('source_line_id', $line->id);
                        });
                    })
                    ->exists();

                if ($alreadyPosted) {
                    continue;
                }

                $reservations = StockReservation::where('status', 'active')
                    ->where(function ($q) use ($line) {
                        $q->where(function ($sub) use ($line) {
                            $sub->where('dwo_line_id', $line->id);
                        });
                    })
                    ->lockForUpdate()
                    ->get();

                $remainingToDeduct = $qtyToIssue;

                foreach ($reservations as $res) {
                    if ($remainingToDeduct <= 0) {
                        break;
                    }

                    $deductQty = min($remainingToDeduct, (float) $res->qty_reserved);
                    $stockItem = StockItem::lockForUpdate()->find($res->stock_item_id);

                    if ($stockItem) {
                        $movements[] = $this->writeDispatchMovement($note, $dwo, $line, $stockItem, $deductQty, $performedBy);
                        $stockItem->qty_on_hand = (float) bcsub((string) $stockItem->qty_on_hand, (string) $deductQty, 4);
                        $stockItem->qty_reserved = (float) max(0, bcsub((string) $stockItem->qty_reserved, (string) $deductQty, 4));
                        $stockItem->recalculateAvailable();
                        $this->balances->assertNonNegative($stockItem);
                        $stockItem->save();
                    }

                    if ((float) $res->qty_reserved <= $deductQty + 0.0001) {
                        $res->markFulfilled();
                    } else {
                        $res->qty_reserved = (float) bcsub((string) $res->qty_reserved, (string) $deductQty, 4);
                        $res->save();
                    }

                    $remainingToDeduct -= $deductQty;
                }

                if ($remainingToDeduct > 0.0001) {
                    $availableStockItems = StockItem::where('catalog_item_id', $line->catalog_item_id)
                        ->when($line->catalog_variant_id, fn ($q) => $q->where('catalog_variant_id', $line->catalog_variant_id))
                        ->where('status', 'active')
                        ->where('qty_on_hand', '>', 0)
                        ->orderBy('created_at', 'asc')
                        ->lockForUpdate()
                        ->get();

                    foreach ($availableStockItems as $stockItem) {
                        if ($remainingToDeduct <= 0) {
                            break;
                        }

                        $deductQty = min($remainingToDeduct, (float) $stockItem->qty_on_hand);
                        $movements[] = $this->writeDispatchMovement($note, $dwo, $line, $stockItem, $deductQty, $performedBy);
                        $stockItem->qty_on_hand = (float) bcsub((string) $stockItem->qty_on_hand, (string) $deductQty, 4);
                        $stockItem->recalculateAvailable();
                        $this->balances->assertNonNegative($stockItem);
                        $stockItem->save();
                        $remainingToDeduct -= $deductQty;
                    }
                }

                // Track the delivered quantity for this line to update DWO line after stock posting
                // This MUST match the quantity actually posted to stock
                $lineDeliveredQuantities[$line->id] = $qtyToIssue;
            }

            // Update DWO line delivered quantities and item status (authoritative delivery event)
            // The qty_delivered is set to exactly what was posted to stock
            foreach ($lineDeliveredQuantities as $lineId => $qtyDelivered) {
                $line = $dwo->lines()->find($lineId);
                if ($line) {
                    $line->qty_delivered = $qtyDelivered;
                    $line->item_status = $qtyDelivered >= $line->qty_allocated
                        ? \App\Modules\Logistics\Enums\DwoLineStatus::Delivered
                        : \App\Modules\Logistics\Enums\DwoLineStatus::PartiallyDelivered;
                    $line->save();
                }
            }

            // Update DWO status based on line delivery status
            $this->updateDwoDeliveryStatus($dwo);

            $note->stock_posted_at = now();
            $note->stock_posted_by = $performedBy;
            $note->status = 'confirmed';
            $note->updated_by = $performedBy;
            $note->save();

            Log::info('[Logistics] Delivery Note confirmed — SALES_DISPATCH posted', [
                'dn_id' => $note->id,
                'dn_number' => $note->dn_number,
                'dwo_id' => $dwo->id,
                'movements' => count($movements),
            ]);

            return $movements;
        });
    }

    private function writeDispatchMovement(
        DeliveryNote $note,
        DeliveryWorkOrder $dwo,
        DwoLine $line,
        StockItem $stockItem,
        float $quantity,
        string $performedBy
    ): StockMovement {
        $catalogItem = $line->catalogItem;
        $unitCost = $stockItem->unit_cost;
        $currency = $stockItem->currency ?? 'TND';

        return StockMovement::create([
            'reference' => $this->generateMovementReference(),
            'movement_type' => 'SALES_DISPATCH',
            'source_document_type' => DeliveryNote::class,
            'source_document_id' => $note->id,
            'source_line_id' => $line->id,
            'catalog_item_id' => $line->catalog_item_id,
            'catalog_variant_id' => $line->catalog_variant_id ?? $stockItem->catalog_variant_id,
            'stock_lot_id' => $stockItem->stock_lot_id,
            'quantity' => (float) bcmul('-1', (string) $quantity, 4),
            'uom_id' => $catalogItem?->uom_id,
            'from_location_id' => $stockItem->location_id,
            'to_location_id' => null,
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost ? (float) bcmul((string) $quantity, (string) $unitCost, 3) : null,
            'currency' => $currency,
            'reason' => "Delivery Note {$note->dn_number} (DWO {$dwo->dwo_number})",
            'metadata' => [
                'delivery_note_id' => $note->id,
                'dn_number' => $note->dn_number,
                'dwo_id' => $dwo->id,
                'dwo_number' => $dwo->dwo_number,
                'sales_order_id' => $dwo->sales_order_id,
                'so_item_id' => $line->so_item_id,
            ],
            'performed_by' => $performedBy,
            'performed_at' => now(),
        ]);
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

    /**
     * Update DWO delivery status based on line delivery status.
     */
    private function updateDwoDeliveryStatus(DeliveryWorkOrder $dwo): void
    {
        $dwo->loadMissing('lines');

        $allDelivered = $dwo->lines->every(fn ($line) => $line->item_status === \App\Modules\Logistics\Enums\DwoLineStatus::Delivered);
        $anyDelivered = $dwo->lines->contains(fn ($line) => $line->item_status === \App\Modules\Logistics\Enums\DwoLineStatus::Delivered || $line->item_status === \App\Modules\Logistics\Enums\DwoLineStatus::PartiallyDelivered);

        if ($allDelivered) {
            $dwo->status = \App\Modules\Logistics\Enums\DwoStatus::Delivered;
            $dwo->delivered_at = now();
        } elseif ($anyDelivered) {
            $dwo->status = \App\Modules\Logistics\Enums\DwoStatus::PartiallyDelivered;
            $dwo->delivered_at = now();
        }

        $dwo->save();
    }
}
