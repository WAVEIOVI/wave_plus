<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Catalog\Models\EquipmentBlueprint
 */
class EquipmentBlueprintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->getTranslations('name'),
            'description' => $this->getTranslations('description'),
            'status' => $this->status,
            'equipment_class_id' => $this->equipment_class_id,
            'working_pressure_bar' => $this->working_pressure_bar,
            'fire_class_rating' => $this->fire_class_rating,
            'shelf_life_years' => $this->shelf_life_years,
            'blueprint_options_count' => $this->blueprint_options_count ?? ($this->relationLoaded('blueprintOptions') ? $this->blueprintOptions->count() : null),
            'servicing_items_count' => $this->servicing_items_count ?? ($this->relationLoaded('servicingItems') ? $this->servicingItems->count() : null),

            // Relations
            'equipment_class' => new EquipmentClassResource($this->whenLoaded('equipmentClass')),
            'equipment_class_rel' => new EquipmentClassResource($this->whenLoaded('equipmentClass')),
            'guidelines' => GuidelineResource::collection($this->whenLoaded('guidelines')),

            'blueprint_options' => BlueprintOptionResource::collection($this->whenLoaded('blueprintOptions')),
            'intervention_cycles' => BlueprintInterventionCycleResource::collection($this->whenLoaded('interventionCycles')),
            'servicing_items' => BlueprintServicingResource::collection($this->whenLoaded('servicingItems')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
