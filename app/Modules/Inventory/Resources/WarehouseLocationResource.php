<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inventory\Models\WarehouseLocation
 */
class WarehouseLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zone_id' => $this->zone_id,
            'barcode' => $this->barcode,
            'full_path' => $this->full_path,
            'aisle' => $this->aisle,
            'rack' => $this->rack,
            'shelf' => $this->shelf,
            'bin' => $this->bin,
            'location_type' => $this->location_type,
            'max_weight_kg' => $this->max_weight_kg ? (float) $this->max_weight_kg : null,
            'max_volume_m3' => $this->max_volume_m3 ? (float) $this->max_volume_m3 : null,
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,

            // Relations — zone + site breadcrumb
            'zone' => $this->whenLoaded('zone', function () {
                $zone = $this->zone;

                return [
                    'id' => $zone->id,
                    'code' => $zone->code,
                    'name' => $zone->getTranslations('name'),
                    'site' => $zone->relationLoaded('site')
                        ? [
                            'id' => $zone->site->id,
                            'code' => $zone->site->code,
                            'name' => $zone->site->getTranslations('name'),
                        ]
                        : null,
                ];
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
