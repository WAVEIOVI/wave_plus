<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Catalog\Models\CatalogCategory
 */
class CatalogCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'parent_id' => $this->parent_id,
            'item_type_scope' => $this->item_type_scope,
            'item_subtype_scope' => $this->item_subtype_scope,
            'icon' => $this->icon,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'parent' => new CatalogCategoryResource($this->whenLoaded('parent')),
            'children' => $this->when(
                $this->relationLoaded('childrenRecursive'),
                CatalogCategoryResource::collection($this->childrenRecursive),
                CatalogCategoryResource::collection($this->whenLoaded('children')),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
