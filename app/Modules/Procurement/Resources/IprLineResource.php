<?php

namespace App\Modules\Procurement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Procurement\Models\IprLine
 */
class IprLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ipr_id' => $this->ipr_id,
            'catalog_item_id' => $this->catalog_item_id,
            'variant_id' => $this->variant_id,

            // Ad-hoc item fields
            'is_ad_hoc' => (bool) $this->is_ad_hoc,
            'ad_hoc_name' => $this->ad_hoc_name,
            'ad_hoc_sku' => $this->ad_hoc_sku,

            'qty_requested' => (float) $this->qty_requested,
            'qty_current_stock' => $this->qty_current_stock !== null ? (float) $this->qty_current_stock : null,
            'qty_on_order' => $this->qty_on_order !== null ? (float) $this->qty_on_order : null,
            'suggested_supplier_id' => $this->suggested_supplier_id,
            'estimated_unit_cost' => $this->estimated_unit_cost !== null ? (float) $this->estimated_unit_cost : null,
            'estimated_total_ht' => $this->estimated_total_ht !== null ? (float) $this->estimated_total_ht : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Loaded Relationships
            'catalog_item' => $this->whenLoaded('catalogItem'),
            'variant' => $this->whenLoaded('variant'),
            'suggested_supplier' => $this->whenLoaded('suggestedSupplier'),
        ];
    }
}
