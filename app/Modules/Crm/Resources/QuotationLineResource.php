<?php

declare(strict_types=1);

namespace App\Modules\Crm\Resources;

use App\Modules\Catalog\Resources\CatalogItemResource;
use App\Modules\Catalog\Resources\CatalogVariantResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Crm\Models\QuotationLine
 */
class QuotationLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quotation_id' => $this->quotation_id,
            'catalog_item_id' => $this->catalog_item_id,
            'variant_id' => $this->variant_id,
            'is_ad_hoc' => $this->is_ad_hoc,
            'ad_hoc_name' => $this->ad_hoc_name,
            'ad_hoc_sku' => $this->ad_hoc_sku,
            'description_override' => $this->description_override,
            'qty' => (float) $this->qty,
            'internal_cost' => (float) $this->internal_cost,
            'actual_margin_pct' => (float) $this->actual_margin_pct,
            'unit_price' => (float) $this->unit_price,
            'discount_pct' => (float) $this->discount_pct,
            'unit_price_after_discount' => (float) $this->unit_price_after_discount,
            'tax_class_id' => $this->tax_class_id,
            'tva_rate_pct' => (float) $this->tva_rate_pct,
            'total_ht' => (float) $this->total_ht,
            'total_tva' => (float) $this->total_tva,
            'total_ttc' => (float) $this->total_ttc,
            'sort_order' => $this->sort_order,
            'note' => $this->note,
            'item_type_snapshot' => $this->item_type_snapshot,
            'description_snapshot' => $this->description_snapshot,
            'sku_snapshot' => $this->sku_snapshot,
            'uom_code_snapshot' => $this->uom_code_snapshot,
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
