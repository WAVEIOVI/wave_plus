<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Catalog\Models\CatalogOption
 */
class CatalogOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'catalog_item_id' => $this->catalog_item_id,
            'name' => $this->getTranslations('name'),
            'sort_order' => $this->sort_order,
            'ui_control' => $this->ui_control ?? 'select',
            'values' => CatalogOptionValueResource::collection($this->whenLoaded('values')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
