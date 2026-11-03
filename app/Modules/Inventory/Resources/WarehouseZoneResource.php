<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inventory\Models\WarehouseZone
 */
class WarehouseZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'code' => $this->code,
            'name' => $this->getTranslations('name'),
            'zone_type' => $this->zone_type,
            'status' => $this->status,
            'notes' => $this->notes,

            // Relations
            'site' => $this->whenLoaded('site', fn () => [
                'id' => $this->site->id,
                'code' => $this->site->code,
                'name' => $this->site->getTranslations('name'),
            ]),

            // Counts
            'locations_count' => $this->whenCounted('locations', (int) $this->locations_count),

            // Nested locations (when eager-loaded on show)
            'locations' => $this->whenLoaded('locations', fn () => WarehouseLocationResource::collection($this->locations)
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
