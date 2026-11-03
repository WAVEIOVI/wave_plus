<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inventory\Models\WarehouseSite
 */
class WarehouseSiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->getTranslations('name'),
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            'notes' => $this->notes,

            // Relations
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager?->id,
                'name' => $this->manager?->name,
            ]),

            // Counts
            'zones_count' => $this->whenCounted('zones', (int) $this->zones_count),
            'locations_count' => $this->whenCounted('locations', (int) $this->locations_count),

            // Nested zones (when eager-loaded on show)
            'zones' => $this->whenLoaded('zones', fn () => WarehouseZoneResource::collection($this->zones)
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
