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

            // Mobile warehouse fields
            'site_type' => $this->site_type ?? 'fixed',
            'vehicle_id' => $this->vehicle_id,
            'mobile_status' => $this->mobile_status,
            'current_location' => $this->current_location,
            'location_notes' => $this->location_notes,

            // Relations
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager?->id,
                'name' => $this->manager?->name,
            ]),

            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id' => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'label' => $this->vehicle->label,
                'type' => $this->vehicle->type,
            ] : null),

            'current_assignment' => $this->whenLoaded(
                'currentAssignment',
                fn () => $this->currentAssignment
                    ? new MobileWarehouseAssignmentResource($this->currentAssignment)
                    : null,
            ),

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
