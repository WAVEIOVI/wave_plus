<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Hr\Models\VehicleChecklist
 */
class VehicleChecklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'checklist_template_id' => $this->checklist_template_id,
            'checklist_type' => $this->checklist_type,
            'title' => $this->title,
            'completed_at' => $this->completed_at ? \Illuminate\Support\Carbon::parse($this->completed_at)->toISOString() : null,
            'completed_by_employee_id' => $this->completed_by_employee_id,
            'driver_id' => $this->driver_id,
            'odometer_km' => $this->odometer_km,
            'location' => $this->location,
            'checklist_items' => $this->checklist_items,
            'overall_status' => $this->overall_status,
            'items_passed' => $this->items_passed,
            'items_failed' => $this->items_failed,
            'items_total' => $this->items_total,
            'pass_rate' => $this->pass_rate,
            'has_issues' => (bool) $this->has_issues,
            'issues_found' => $this->issues_found,
            'notes' => $this->notes,
            'recommendations' => $this->recommendations,
            'requires_maintenance' => (bool) $this->requires_maintenance,
            'maintenance_record_id' => $this->maintenance_record_id,
            'mission_id' => $this->mission_id,
            'dwo_id' => $this->dwo_id,
            'swo_id' => $this->swo_id,
            'created_at' => $this->created_at ? \Illuminate\Support\Carbon::parse($this->created_at)->toISOString() : null,
            'updated_at' => $this->updated_at ? \Illuminate\Support\Carbon::parse($this->updated_at)->toISOString() : null,
        ];
    }
}
