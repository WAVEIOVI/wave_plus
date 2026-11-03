<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Services\StockBalanceService;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\DwoLine;
use Illuminate\Support\Facades\DB;

/**
 * DWO-owned stock reservation.
 *
 * Rules:
 * - Reserve only the allocated quantity (never the full SO quantity).
 * - Insufficient stock does NOT block allocation — shortage is recorded.
 * - Reservation qty never exceeds real reservable quantity.
 * - Release via StockReservation::markCancelled only (no double decrement).
 */
class DwoReservationService
{
    public function __construct(
        private readonly StockBalanceService $balances,
    ) {}

    /**
     * Attempt reservation for all allocated lines on the DWO.
     * Partial reservation is allowed; shortage is persisted on the line.
     */
    public function reserveForDwo(DeliveryWorkOrder $dwo, string $performedBy): void
    {
        DB::transaction(function () use ($dwo, $performedBy) {
            $dwo->loadMissing(['lines.catalogItem']);

            foreach ($dwo->lines as $line) {
                $this->reserveForLine($dwo, $line, $performedBy);
            }
        });
    }

    /**
     * Reserve for a single DWO line up to qty_allocated.
     */
    public function reserveForLine(DeliveryWorkOrder $dwo, DwoLine $line, string $performedBy): void
    {
        $catalogItem = $line->catalogItem;
        if (! $catalogItem || ! $catalogItem->is_stockable) {
            $line->qty_reserved = 0;
            $line->qty_shortage = 0;
            $line->save();

            return;
        }

        $alreadyReserved = (float) StockReservation::where('reservable_type', DeliveryWorkOrder::class)
            ->where('reservable_id', $dwo->id)
            ->where('dwo_line_id', $line->id)
            ->where('status', 'active')
            ->sum('qty_reserved');

        $qtyNeeded = (float) $line->qty_allocated - $alreadyReserved;
        if ($qtyNeeded <= 0.0001) {
            $line->qty_reserved = round($alreadyReserved, 3);
            $line->qty_shortage = 0;
            $line->save();

            return;
        }

        $stockItemsQuery = StockItem::where('catalog_item_id', $line->catalog_item_id)
            ->when($line->catalog_variant_id, fn ($q) => $q->where('catalog_variant_id', $line->catalog_variant_id))
            ->where('status', 'active')
            ->where('qty_available', '>', 0)
            ->orderBy('created_at', 'asc')
            ->lockForUpdate();

        if ($dwo->warehouse_id) {
            $stockItemsQuery->whereHas('location.zone', function ($q) use ($dwo) {
                $q->where('site_id', $dwo->warehouse_id);
            });
        }

        $stockItems = $stockItemsQuery->get();
        $newlyReserved = 0.0;

        foreach ($stockItems as $stockItem) {
            if ($qtyNeeded <= 0) {
                break;
            }

            $available = (float) $stockItem->qty_available;
            $reserveQty = min($qtyNeeded, $available);
            if ($reserveQty <= 0) {
                continue;
            }

            StockReservation::create([
                'stock_item_id'       => $stockItem->id,
                'catalog_item_id'     => $catalogItem->id,
                'catalog_variant_id'  => $line->catalog_variant_id ?? $stockItem->catalog_variant_id,
                'reservable_type'     => DeliveryWorkOrder::class,
                'reservable_id'       => $dwo->id,
                'dwo_line_id'         => $line->id,
                'sales_order_line_id' => $line->so_item_id,
                'qty_reserved'        => $reserveQty,
                'status'              => 'active',
                'reserved_at'         => now(),
                'expires_at'          => $dwo->scheduled_date ? $dwo->scheduled_date->copy()->addDays(7) : now()->addDays(7),
                'reserved_by'         => $performedBy,
                'reason'              => "DWO reservation: {$dwo->dwo_number} line {$line->id}",
            ]);

            $stockItem->qty_reserved = (float) bcadd((string) $stockItem->qty_reserved, (string) $reserveQty, 4);
            $stockItem->recalculateAvailable();
            $this->balances->assertNonNegative($stockItem);
            $stockItem->save();

            $qtyNeeded -= $reserveQty;
            $newlyReserved += $reserveQty;
        }

        $totalReserved = round($alreadyReserved + $newlyReserved, 3);
        $line->qty_reserved = $totalReserved;
        $line->qty_shortage = round(max(0, (float) $line->qty_allocated - $totalReserved), 3);
        $line->save();
    }

    /**
     * Release reservation for a single DWO line.
     * Used when a substitution is approved for one line only.
     */
    public function releaseForLine(DeliveryWorkOrder $dwo, DwoLine $line, string $performedBy): void
    {
        DB::transaction(function () use ($dwo, $line) {
            $reservations = StockReservation::where('reservable_type', DeliveryWorkOrder::class)
                ->where('reservable_id', $dwo->id)
                ->where('dwo_line_id', $line->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $res) {
                $res->markCancelled('DWO line substitution reservation released');
            }

            // Also handle fallback reservations without dwo_line_id but matching catalog item
            $fallbackReservations = StockReservation::where('reservable_type', DeliveryWorkOrder::class)
                ->where('reservable_id', $dwo->id)
                ->where('catalog_item_id', $line->catalog_item_id)
                ->when($line->catalog_variant_id, fn ($q) => $q->where('catalog_variant_id', $line->catalog_variant_id))
                ->whereNull('dwo_line_id')
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($fallbackReservations as $res) {
                $res->markCancelled('DWO line substitution reservation released (fallback)');
            }

            $line->qty_reserved = 0;
            $line->qty_shortage = round(max(0, (float) $line->qty_allocated - (float) ($line->qty_delivered ?? 0)), 3);
            $line->save();
        });
    }

    /**
     * Release all active stock reservations for a DWO.
     * Uses markCancelled() only — that method owns the stock qty_reserved decrement.
     */
    public function releaseForDwo(DeliveryWorkOrder $dwo, string $performedBy): void
    {
        DB::transaction(function () use ($dwo) {
            $reservations = StockReservation::where('reservable_type', DeliveryWorkOrder::class)
                ->where('reservable_id', $dwo->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $res) {
                // markCancelled already decrements stock_item.qty_reserved — do NOT double-decrement.
                $res->markCancelled('DWO reservation released');
            }

            foreach ($dwo->lines as $line) {
                $line->qty_reserved = 0;
                $line->qty_shortage = round(max(0, (float) $line->qty_allocated - (float) ($line->qty_delivered ?? 0)), 3);
                $line->save();
            }
        });
    }

    /**
     * Release reservation for undelivered quantity when a DWO is closed.
     * Delivered qty should already have reservations fulfilled via Delivery Note posting.
     */
    public function releaseUndeliveredForDwo(DeliveryWorkOrder $dwo, string $performedBy): void
    {
        DB::transaction(function () use ($dwo) {
            $dwo->loadMissing('lines');

            $active = StockReservation::where('reservable_type', DeliveryWorkOrder::class)
                ->where('reservable_id', $dwo->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($active as $res) {
                $res->markCancelled('Released undelivered reservation on DWO close');
            }

            foreach ($dwo->lines as $line) {
                $line->qty_reserved = 0;
                $line->save();
            }
        });
    }
}
