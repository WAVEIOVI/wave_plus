<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Catalog\Models\CatalogVariant
 */
class CatalogVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'catalog_item_id' => $this->catalog_item_id,
            'is_default' => $this->is_default,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'price' => $this->price,
            'cost_price' => $this->cost_price,
            'markup' => $this->markup,
            'current_stock' => $this->current_stock,
            'tax_class' => $this->tax_class,
            'stock' => $this->stock,
            'min_stock_level' => $this->min_stock_level,
            'reorder_point' => $this->reorder_point,
            'reorder_qty' => $this->reorder_qty,
            'lead_time_days' => $this->lead_time_days,
            'status' => $this->status,
            'option_values' => CatalogOptionValueResource::collection($this->whenLoaded('optionValues')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
