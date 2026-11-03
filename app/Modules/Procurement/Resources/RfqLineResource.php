<?php

namespace App\Modules\Procurement\Resources;

use App\Modules\Catalog\Resources\CatalogItemResource;
use App\Modules\Catalog\Resources\CatalogVariantResource;
use App\Modules\Catalog\Resources\UnitOfMeasureResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Procurement\Models\RfqLine
 */
class RfqLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rfq_id' => $this->rfq_id,
            'catalog_item_id' => $this->catalog_item_id,
            'variant_id' => $this->variant_id,

            // Ad-hoc fields
            'is_ad_hoc' => (bool) $this->is_ad_hoc,
            'ad_hoc_name' => $this->ad_hoc_name,
            'ad_hoc_sku' => $this->ad_hoc_sku,

            'description_for_supplier' => $this->description_for_supplier,
            'qty_requested' => (float) $this->qty_requested,
            'unit_id' => $this->unit_id,
            'target_price_ht' => $this->target_price_ht !== null ? (float) $this->target_price_ht : null,
            'required_certifications' => $this->required_certifications,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'catalog_item' => new CatalogItemResource($this->whenLoaded('catalogItem')),
            'variant' => new CatalogVariantResource($this->whenLoaded('variant')),
            'unit' => new UnitOfMeasureResource($this->whenLoaded('unit')),
        ];
    }
}
