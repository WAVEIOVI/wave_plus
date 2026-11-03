<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Hr\Models\VehicleActivityLog
 */
class VehicleActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'event_type' => $this->event_type,
            'event_title' => $this->event_title,
            'event_description' => $this->event_description,
            'event_date' => $this->event_date ? \Illuminate\Support\Carbon::parse($this->event_date)->toISOString() : null,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'metadata' => $this->metadata,
            'actor_employee_id' => $this->actor_employee_id,
            'actor_name' => $this->actor_name,
            'location' => $this->location,
            'odometer_km' => $this->odometer_km,
            'created_at' => $this->created_at ? \Illuminate\Support\Carbon::parse($this->created_at)->toISOString() : null,
            'actor_employee' => $this->whenLoaded('actorEmployee', fn () => $this->actorEmployee ? [
                'id' => $this->actorEmployee->getKey(),
                'name' => $this->actorEmployee->getAttribute('name'),
                'email' => $this->actorEmployee->getAttribute('email'),
            ] : null),
        ];
    }
}
