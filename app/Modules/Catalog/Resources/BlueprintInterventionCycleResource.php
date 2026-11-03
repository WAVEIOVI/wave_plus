<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Catalog\Models\BlueprintInterventionCycle
 */
class BlueprintInterventionCycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'intervention_type' => $this->intervention_type,
            'first_interval_months' => $this->first_interval_months,
            'recurring_interval_months' => $this->recurring_interval_months,
            'notes' => $this->notes,
        ];
    }
}
