<?php

declare(strict_types=1);

namespace App\Modules\Crm\Resources;

use App\Modules\Catalog\Resources\CatalogItemResource;
use App\Modules\Catalog\Resources\CatalogVariantResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Crm\Models\PriceListLine
 */
class PriceListLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'price_list_id' => $this->price_list_id,
            'catalog_item_id' => $this->catalog_item_id,
            'variant_id' => $this->variant_id,
            'price_computation' => $this->price_computation,
            'fixed_price' => (float) $this->fixed_price,
            'discount_pct' => (float) $this->discount_pct,
            'min_qty' => (float) $this->min_qty,
            'max_qty' => (float) $this->max_qty,
            'computed_price' => (float) $this->computed_price,
            'notes' => $this->notes,
            'internal_cost' => (float) $this->internal_cost,
            'sales_price' => (float) $this->sales_price,
            'source_type' => $this->source_type,
            'source_blueprint_id' => $this->source_blueprint_id,
            'source_blueprint_option_values' => $this->source_blueprint_option_values,
            'source_servicing_id' => $this->source_servicing_id,
            'source_consumable_id' => $this->source_consumable_id,
            'calculation_snapshot' => $this->calculation_snapshot,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'catalog_item' => new CatalogItemResource($this->whenLoaded('catalogItem')),
            'variant' => new CatalogVariantResource($this->whenLoaded('variant')),
        ];
    }
}
