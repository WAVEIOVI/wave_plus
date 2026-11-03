<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\ClientAssets\Models\ClientSiteArea
 */
class ClientSiteAreaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'parent_area_id' => $this->parent_area_id,
            'code' => $this->code,
            'name' => $this->name,
            'area_type' => $this->area_type,
            'classification' => $this->classification,
            'accessibility' => $this->accessibility,
            'level_number' => $this->level_number,
            'description' => $this->description,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'hierarchy_path' => $this->hierarchy_path,
            'equipment_count' => $this->whenCounted('equipment'),
            'parent' => new ClientSiteAreaResource($this->whenLoaded('parent')),
            'children' => ClientSiteAreaResource::collection($this->whenLoaded('children')),
            'all_children' => ClientSiteAreaResource::collection($this->whenLoaded('allChildren')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
