<?php

declare(strict_types=1);

namespace App\Modules\Crm\Resources;

use App\Modules\Catalog\Resources\CatalogItemResource;
use App\Modules\Catalog\Resources\CatalogVariantResource;
use App\Modules\Catalog\Resources\UnitOfMeasureResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Crm\Models\SalesOrderLine
 */
class SalesOrderLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_order_id' => $this->sales_order_id,
            'catalog_item_id' => $this->catalog_item_id,
            'variant_id' => $this->variant_id,
            'is_ad_hoc' => (bool) $this->is_ad_hoc,
            'ad_hoc_name' => $this->ad_hoc_name,
            'ad_hoc_sku' => $this->ad_hoc_sku,
            'item_type_snapshot' => $this->item_type_snapshot instanceof \BackedEnum ? $this->item_type_snapshot->value : $this->item_type_snapshot,
            'description_snapshot' => $this->description_snapshot,
            'sku_snapshot' => $this->sku_snapshot,
            'uom_code_snapshot' => $this->uom_code_snapshot,
            'qty' => (float) $this->qty,
            'reserved_qty' => (float) $this->reserved_qty,
            'fulfilled_qty' => (float) $this->fulfilled_qty,
            'unit_id' => $this->unit_id,
            'unit_price' => $this->unit_price !== null ? (float) $this->unit_price : null,
            'discount_pct' => (float) $this->discount_pct,
            'total_ht' => (float) $this->total_ht,
            'total_tva' => (float) $this->total_tva,
            'total_ttc' => (float) $this->total_ttc,
            'target_site_id' => $this->target_site_id,
            'target_equipment_ids' => $this->target_equipment_ids,
            'fulfillment_status' => $this->fulfillment_status,
            'delivery_date' => $this->delivery_date?->toDateString(),
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
            'unit' => new UnitOfMeasureResource($this->whenLoaded('unit')),
        ];
    }
}
