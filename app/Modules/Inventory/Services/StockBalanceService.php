<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Models\WarehouseLocation;
use Illuminate\Database\UniqueConstraintViolationException;

class StockBalanceService
{
    /**
     * Lock the current-state cache row for this item/variant/location/lot.
     * stock_items is a projection of the movement ledger, never a source of truth.
     */
    public function lockOrCreate(
        string $catalogItemId,
        string $catalogVariantId,
        string $locationId,
        ?string $stockLotId,
        array $defaults = []
    ): StockItem {
        $query = StockItem::query()
            ->where('catalog_item_id', $catalogItemId)
            ->where('catalog_variant_id', $catalogVariantId)
            ->where('location_id', $locationId);

        if ($stockLotId) {
            $query->where('stock_lot_id', $stockLotId);
        } else {
            $query->whereNull('stock_lot_id');
        }

        $item = (clone $query)->lockForUpdate()->first();
        if ($item) {
            return $item;
        }

        try {
            return StockItem::create(array_merge([
                'catalog_item_id' => $catalogItemId,
                'catalog_variant_id' => $catalogVariantId,
                'location_id' => $locationId,
                'stock_lot_id' => $stockLotId,
                'qty_on_hand' => 0.0,
                'qty_reserved' => 0.0,
                'qty_available' => 0.0,
                'qty_in_transit' => 0.0,
                'condition' => 'new',
                'status' => 'active',
                'currency' => 'TND',
            ], $defaults));
        } catch (UniqueConstraintViolationException) {
            $item = (clone $query)->lockForUpdate()->first();
            if (! $item) {
                abort(422, 'Could not lock stock balance for this item and location.');
            }

            return $item;
        }
    }

    public function assertUsableLocation(string $locationId): WarehouseLocation
    {
        $location = WarehouseLocation::query()->find($locationId);
        if (! $location) {
            abort(422, 'Warehouse location was not found.');
        }
        if (! $location->is_active) {
            abort(422, 'Cannot move stock to or from an inactive warehouse location.');
        }

        return $location;
    }

    public function incrementOnHand(StockItem $item, string $qty): void
    {
        $this->assertMutable($item);
        $item->incrementOnHand($qty);
        $this->assertNonNegative($item);
        $item->save();
    }

    public function decrementOnHand(StockItem $item, string $qty, bool $requireAvailable = true): void
    {
        $this->assertMutable($item);

        if ($requireAvailable && bccomp((string) $item->qty_available, $qty, 4) < 0) {
            abort(422, 'Insufficient available stock. Available: '.$item->qty_available.', requested: '.$qty);
        }

        if (bccomp((string) $item->qty_on_hand, $qty, 4) < 0) {
            abort(422, 'Insufficient on-hand stock. On hand: '.$item->qty_on_hand.', requested: '.$qty);
        }

        $item->decrementOnHand($qty);
        $this->assertNonNegative($item);
        $item->save();
    }

    public function assertMutable(StockItem $item): void
    {
        if (in_array($item->status, ['blocked', 'quarantined'], true)) {
            abort(422, "Stock at this location is '{$item->status}' and cannot be moved or adjusted.");
        }
    }

    public function assertNonNegative(StockItem $item): void
    {
        if (bccomp((string) $item->qty_on_hand, '0', 4) < 0) {
            abort(422, 'Negative on-hand stock is not allowed.');
        }
        if (bccomp((string) $item->qty_reserved, '0', 4) < 0) {
            abort(422, 'Negative reserved stock is not allowed.');
        }
        if (bccomp((string) $item->qty_available, '0', 4) < 0) {
            abort(422, 'Available quantity cannot be negative.');
        }
    }
}
