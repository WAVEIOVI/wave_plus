<?php

declare(strict_types=1);

namespace App\Modules\Crm\Resources;

use App\Modules\Catalog\Resources\CatalogItemResource;
use App\Modules\Catalog\Resources\CatalogVariantResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Crm\Models\ContractLine
 */
class ContractLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'catalog_item_id' => $this->catalog_item_id,
            'variant_id' => $this->variant_id,
            'is_ad_hoc' => $this->is_ad_hoc,
            'ad_hoc_name' => $this->ad_hoc_name,
            'ad_hoc_sku' => $this->ad_hoc_sku,
            'contracted_qty' => (float) $this->contracted_qty,
            'contracted_unit_price' => (float) $this->contracted_unit_price,
            'called_off_qty' => (float) $this->called_off_qty,
            'remaining_qty' => (float) $this->remaining_qty,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'catalog_item' => new CatalogItemResource($this->whenLoaded('catalogItem')),
            'variant' => new CatalogVariantResource($this->whenLoaded('variant')),
        ];
    }
}
