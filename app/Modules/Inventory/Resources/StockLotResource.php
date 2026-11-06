<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inventory\Models\StockLot
 */
class StockLotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lot_number' => $this->lot_number,
            'status' => $this->status,
            'manufacture_date' => $this->manufacture_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'days_until_expiry' => $this->expiry_date
                ? (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false)
                : null,
            'notes' => $this->notes,

            // Relations
            'catalog_item' => $this->whenLoaded('catalogItem', fn () => [
                'id' => $this->catalogItem->id,
                'code' => $this->catalogItem->code,
                'name' => $this->catalogItem->getTranslations('name'),
            ]),

            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier ? ($this->supplier->commercial_name ?: $this->supplier->legal_name) : null,
            ]),

            // Counts
            'serial_numbers_count' => $this->whenCounted('serialNumbers', (int) $this->serial_numbers_count),
            'stock_items_count' => $this->whenCounted('stockItems', (int) $this->stock_items_count),

            // Nested (when show)
            'serial_numbers' => $this->whenLoaded('serialNumbers'),
            'stock_items' => $this->whenLoaded('stockItems', fn () => StockItemResource::collection($this->stockItems)
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
