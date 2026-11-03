<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Hr\Models\VehicleMaintenanceRecord
 */
class VehicleMaintenanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maintenance_number' => $this->maintenance_number,
            'vehicle_id' => $this->vehicle_id,
            'maintenance_type' => $this->maintenance_type,
            'priority' => $this->priority,
            'scheduled_date' => $this->scheduled_date ? \Illuminate\Support\Carbon::parse($this->scheduled_date)->format('Y-m-d') : null,
            'completed_date' => $this->completed_date ? \Illuminate\Support\Carbon::parse($this->completed_date)->format('Y-m-d') : null,
            'odometer_km' => $this->odometer_km,
            'workshop_provider_id' => $this->workshop_provider_id,
            'workshop_unit_id' => $this->workshop_unit_id,
            'workshop_name' => $this->workshop_name,
            'title' => $this->title,
            'description' => $this->description,
            'work_items' => $this->work_items,
            'parts_used' => $this->parts_used,
            'labor_cost' => (float) $this->labor_cost,
            'parts_cost' => (float) $this->parts_cost,
            'total_cost' => (float) $this->total_cost,
            'currency' => $this->currency,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date ? \Illuminate\Support\Carbon::parse($this->invoice_date)->format('Y-m-d') : null,
            'next_service_date' => $this->next_service_date ? \Illuminate\Support\Carbon::parse($this->next_service_date)->format('Y-m-d') : null,
            'next_service_km' => $this->next_service_km,
            'status' => $this->status,
            'notes' => $this->notes,
            'documents' => $this->documents,
            'is_completed' => $this->is_completed,
            'is_overdue' => $this->is_overdue,
            'workshop_provider' => $this->whenLoaded('workshopProvider', fn () => $this->workshopProvider ? [
                'id' => $this->workshopProvider->getKey(),
                'name' => $this->workshopProvider->getAttribute('name'),
            ] : null),
            'workshop_unit' => $this->whenLoaded('workshopUnit', fn () => $this->workshopUnit ? [
                'id' => $this->workshopUnit->getKey(),
                'name' => $this->workshopUnit->getAttribute('name'),
                'code' => $this->workshopUnit->getAttribute('code'),
            ] : null),
            'created_at' => $this->created_at ? \Illuminate\Support\Carbon::parse($this->created_at)->toISOString() : null,
            'updated_at' => $this->updated_at ? \Illuminate\Support\Carbon::parse($this->updated_at)->toISOString() : null,
        ];
    }
}
