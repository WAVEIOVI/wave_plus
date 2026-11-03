<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Catalog\Models\GuidelineStep
 */
class GuidelineStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guideline_id' => $this->guideline_id,
            'sort_order' => $this->sort_order,
            'instruction' => $this->getTranslations('instruction'),
            'step_type' => $this->step_type,
            'is_required' => $this->is_required,
            'expected_value' => $this->expected_value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
