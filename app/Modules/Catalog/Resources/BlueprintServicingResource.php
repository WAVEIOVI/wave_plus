<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Catalog\Models\BlueprintServicing
 */
class BlueprintServicingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'servicing_item_id' => $this->servicing_item_id,
            'applicability_conditions' => $this->applicability_conditions,
            'servicing_item' => new CatalogItemResource($this->whenLoaded('servicingItem')),
            'consumables' => BlueprintServicingConsumableResource::collection($this->whenLoaded('consumables')),
        ];
    }
}
