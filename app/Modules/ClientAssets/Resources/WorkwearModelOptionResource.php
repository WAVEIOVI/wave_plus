<?php

namespace App\Modules\ClientAssets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkwearModelOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workwear_model_id' => $this->workwear_model_id,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
            'ui_control' => $this->ui_control,
            'values' => WorkwearModelOptionValueResource::collection($this->whenLoaded('values')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
