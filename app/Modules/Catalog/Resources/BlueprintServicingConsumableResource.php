<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Catalog\Models\BlueprintServicingConsumable
 */
class BlueprintServicingConsumableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'blueprint_servicing_id' => $this->blueprint_servicing_id,
            'consumable_item_id' => $this->consumable_item_id,
            'calculation_type' => $this->calculation_type ?? 'fixed',
            'calculation_source_option_id' => $this->calculation_source_option_id,
            'calculation_method' => $this->calculation_method,
            'calculation_parameter' => $this->calculation_parameter !== null ? (float) $this->calculation_parameter : null,
            'fixed_quantity' => $this->fixed_quantity !== null ? (float) $this->fixed_quantity : null,
            'applicability_conditions' => $this->applicability_conditions,
            'calculation_source_option' => new BlueprintOptionResource($this->whenLoaded('calculationSourceOption')),
            'consumable_item' => new CatalogItemResource($this->whenLoaded('consumableItem')),
        ];
    }
}
