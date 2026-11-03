<?php

declare(strict_types=1);

namespace App\Modules\Crm\Resources;

use App\Modules\Catalog\Resources\CatalogItemResource;
use App\Modules\Catalog\Resources\CatalogVariantResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Crm\Models\PriceRequestLine
 */
class PriceRequestLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'price_request_id' => $this->price_request_id,
            'catalog_item_id' => $this->catalog_item_id,
            'variant_id' => $this->variant_id,
            'is_ad_hoc' => $this->is_ad_hoc,
            'ad_hoc_name' => $this->ad_hoc_name,
            'ad_hoc_sku' => $this->ad_hoc_sku,
            'qty_requested' => (float) $this->qty_requested,
            'internal_cost' => (float) $this->internal_cost,
            'target_margin_pct' => (float) $this->target_margin_pct,
            'suggested_price' => (float) $this->suggested_price,
            'proposed_price' => (float) $this->proposed_price,
            'discount_pct' => (float) $this->discount_pct,
            'actual_margin_pct' => (float) $this->actual_margin_pct,
            'tva_rate_pct' => (float) ($this->tva_rate_pct ?? 0),
            'notes' => $this->notes,
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
