<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inventory\Models\StockItem
 */
class StockItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'catalog_item_id' => $this->catalog_item_id,
            'location_id' => $this->location_id,
            'stock_lot_id' => $this->stock_lot_id,
            'catalog_variant_id' => $this->catalog_variant_id,

            // ── Quantity buckets ────────────────────────────────────
            'qty_on_hand' => (float) $this->qty_on_hand,
            'qty_reserved' => (float) $this->qty_reserved,
            'qty_available' => (float) $this->qty_available,
            'qty_in_transit' => (float) $this->qty_in_transit,

            // ── Cost ────────────────────────────────────────────────
            'unit_cost' => $this->unit_cost ? (float) $this->unit_cost : null,
            'currency' => $this->currency,
            'line_value' => $this->unit_cost
                ? round((float) $this->qty_on_hand * (float) $this->unit_cost, 3)
                : null,

            // ── Status ──────────────────────────────────────────────
            'condition' => $this->condition,
            'status' => $this->status,

            // ── Relations ───────────────────────────────────────────
            'catalog_variant' => $this->whenLoaded('catalogVariant', function () {
                $variant = $this->catalogVariant;

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                ];
            }),

            'catalog_item' => $this->whenLoaded('catalogItem', function () {
                $item = $this->catalogItem;

                $uomData = null;
                if ($item->relationLoaded('uom') && $item->uom) {
                    /** @var \App\Modules\Catalog\Models\UnitOfMeasure $uom */
                    $uom = $item->uom;
                    $uomData = [
                        'id' => $uom->id,
                        'name' => $uom->getTranslations('name'),
                        'symbol' => $uom->code,
                    ];
                }

                $categoryData = null;
                if ($item->relationLoaded('category') && $item->category) {
                    /** @var \App\Modules\Catalog\Models\CatalogCategory $category */
                    $category = $item->category;
                    $categoryData = [
                        'id' => $category->id,
                        'name' => $category->getTranslations('name'),
                    ];
                }

                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'sku' => $item->sku,
                    'name' => $item->getTranslations('name'),
                    'item_type' => $item->item_type,
                    'uom' => $uomData,
                    'category' => $categoryData,
                ];
            }),

            'location' => $this->whenLoaded('location', function () {
                $loc = $this->location;

                $zoneData = null;
                if ($loc->relationLoaded('zone')) {
                    /** @var \App\Modules\Inventory\Models\WarehouseZone $zone */
                    $zone = $loc->zone;

                    $siteData = null;
                    if ($zone->relationLoaded('site')) {
                        /** @var \App\Modules\Inventory\Models\WarehouseSite $site */
                        $site = $zone->site;
                        $siteData = [
                            'id' => $site->id,
                            'code' => $site->code,
                            'name' => $site->getTranslations('name'),
                        ];
                    }

                    $zoneData = [
                        'id' => $zone->id,
                        'code' => $zone->code,
                        'name' => $zone->getTranslations('name'),
                        'site' => $siteData,
                    ];
                }

                return [
                    'id' => $loc->id,
                    'barcode' => $loc->barcode,
                    'full_path' => $loc->full_path,
                    'zone' => $zoneData,
                ];
            }),

            'stock_lot' => $this->whenLoaded('stockLot', fn () => [
                'id' => $this->stockLot?->id,
                'lot_number' => $this->stockLot?->lot_number,
                'expiry_date' => $this->stockLot?->expiry_date?->toDateString(),
                'manufacture_date' => $this->stockLot?->manufacture_date?->toDateString(),
                'status' => $this->stockLot?->status,
            ]),

            // ── Timestamps ──────────────────────────────────────────
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
