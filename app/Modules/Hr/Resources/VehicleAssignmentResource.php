<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Hr\Models\VehicleAssignment
 */
class VehicleAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'responsible_employee_id' => $this->responsible_employee_id,
            'custody_type' => $this->custody_type,
            'site_id' => $this->site_id,
            'location_notes' => $this->location_notes,
            'start_date' => $this->start_date ? \Illuminate\Support\Carbon::parse($this->start_date)->toISOString() : null,
            'end_date' => $this->end_date ? \Illuminate\Support\Carbon::parse($this->end_date)->toISOString() : null,
            'is_current' => (bool) $this->is_current,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'odometer_at_start' => $this->odometer_at_start,
            'odometer_at_end' => $this->odometer_at_end,
            'assigned_by' => $this->assigned_by,
            'returned_by' => $this->returned_by,
            'created_at' => $this->created_at ? \Illuminate\Support\Carbon::parse($this->created_at)->toISOString() : null,
            'updated_at' => $this->updated_at ? \Illuminate\Support\Carbon::parse($this->updated_at)->toISOString() : null,
            'responsible_employee' => $this->whenLoaded('responsibleEmployee', fn () => $this->responsibleEmployee ? [
                'id' => $this->responsibleEmployee->getKey(),
                'name' => $this->responsibleEmployee->getAttribute('name') ?? $this->responsibleEmployee->getAttribute('full_name'),
                'email' => $this->responsibleEmployee->getAttribute('email'),
            ] : null),
            'site' => $this->whenLoaded('site', fn () => $this->site ? [
                'id' => $this->site->getKey(),
                'name' => $this->site->getAttribute('name'),
                'code' => $this->site->getAttribute('code'),
            ] : null),
        ];
    }
}
