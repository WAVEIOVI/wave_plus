<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkwearFabricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'composition' => $this->composition,
            'weight_gsm' => $this->weight_gsm,
            'weave_type' => $this->weave_type,
            'properties' => $this->properties ?? [],
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) ($this->sort_order ?? 0),
            'workwear_models_count' => $this->whenCounted('workwearModels'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
