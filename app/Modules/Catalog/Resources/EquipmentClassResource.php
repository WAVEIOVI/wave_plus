<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Catalog\Models\EquipmentClass
 */
class EquipmentClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->getTranslations('name'),
            'name_text' => $this->getTranslation('name', app()->getLocale(), false) ?: ($this->getTranslation('name', 'en') ?: $this->code),
            'description' => $this->getTranslations('description'),
            'parent_id' => $this->parent_id,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'blueprints_count' => $this->whenCounted('blueprints'),
            'equipment_count' => $this->whenCounted('equipment'),
            'parent' => new self($this->whenLoaded('parent')),
            'children' => $this->when(
                $this->relationLoaded('childrenRecursive'),
                self::collection($this->childrenRecursive),
                self::collection($this->whenLoaded('children')),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
