<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin \App\Modules\Hr\Models\ApprovedDriver
 */
class ApprovedDriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_authorization_number' => $this->driver_authorization_number,
            'employee_id' => $this->employee_id,
            'authorization_status' => $this->authorization_status,
            'authorization_date' => $this->authorization_date ? Carbon::parse($this->authorization_date)->format('Y-m-d') : null,
            'expiry_date' => $this->expiry_date ? Carbon::parse($this->expiry_date)->format('Y-m-d') : null,
            'review_date' => $this->review_date ? Carbon::parse($this->review_date)->format('Y-m-d') : null,
            'license_number' => $this->license_number,
            'license_category' => $this->license_category,
            'license_categories' => $this->license_categories ?? [],
            'license_issue_date' => $this->license_issue_date ? Carbon::parse($this->license_issue_date)->format('Y-m-d') : null,
            'license_expiry_date' => $this->license_expiry_date ? Carbon::parse($this->license_expiry_date)->format('Y-m-d') : null,
            'license_issuer' => $this->license_issuer,
            'permitted_vehicle_types' => $this->permitted_vehicle_types ?? [],
            'permitted_vehicle_classes' => $this->permitted_vehicle_classes ?? [],
            'can_drive_rental' => (bool) $this->can_drive_rental,
            'can_drive_client_vehicles' => (bool) $this->can_drive_client_vehicles,
            'restrictions' => $this->restrictions ?? [],
            'max_passengers' => $this->max_passengers,
            'max_cargo_kg' => $this->max_cargo_kg,
            'restriction_notes' => $this->restriction_notes,
            'driver_level' => $this->driver_level,
            'driver_score' => $this->driver_score !== null ? (float) $this->driver_score : null,
            'incident_count' => (int) ($this->incident_count ?? 0),
            'violation_count' => (int) ($this->violation_count ?? 0),
            'last_incident_date' => $this->last_incident_date ? Carbon::parse($this->last_incident_date)->format('Y-m-d') : null,
            'suspended_from' => $this->suspended_from ? Carbon::parse($this->suspended_from)->format('Y-m-d') : null,
            'suspended_until' => $this->suspended_until ? Carbon::parse($this->suspended_until)->format('Y-m-d') : null,
            'suspension_reason' => $this->suspension_reason,
            'notes' => $this->notes,
            'documents' => $this->documents ?? [],
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toISOString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toISOString() : null,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id' => $this->employee->id,
                'first_name' => $this->employee->first_name,
                'last_name' => $this->employee->last_name,
                'full_name' => $this->employee->full_name,
                'matricule' => $this->employee->matricule,
                'employee_number' => $this->employee->matricule,
                'email' => $this->employee->email,
                'phone' => $this->employee->phone,
                'department' => $this->employee->relationLoaded('department') && $this->employee->department ? [
                    'id' => $this->employee->department->id,
                    'name' => $this->employee->department->name,
                ] : null,
                'position' => $this->employee->relationLoaded('position') && $this->employee->position ? [
                    'id' => $this->employee->position->id,
                    'title' => $this->employee->position->title,
                ] : null,
            ] : null),
            'incidents' => $this->whenLoaded('incidents'),
            'activity_log' => $this->whenLoaded('activityLog'),
        ];
    }
}
