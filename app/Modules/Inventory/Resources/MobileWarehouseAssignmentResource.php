<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileWarehouseAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'warehouse_site_id'  => $this->warehouse_site_id,
            'vehicle_id'         => $this->vehicle_id,
            'vehicle'            => $this->whenLoaded('vehicle', fn () => [
                'id'           => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'label'        => $this->vehicle->label,
                'type'         => $this->vehicle->type,
            ]),
            'assigned_from'      => $this->assigned_from?->format('Y-m-d'),
            'assigned_until'     => $this->assigned_until?->format('Y-m-d'),
            'notes'              => $this->notes,
            'is_current'         => $this->is_current,
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),
        ];
    }
}