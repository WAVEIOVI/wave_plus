<?php

namespace App\Modules\ClientAssets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkwearEmployeeModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'model_id' => $this->model_id,
            'quantity' => $this->quantity,
            'size' => $this->size,
            'notes' => $this->notes,
            'assigned_at' => $this->assigned_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'is_active' => $this->ended_at === null,

            'model' => $this->whenLoaded('workwearModel', fn () => $this->workwearModel ? [
                'id' => $this->workwearModel->id,
                'model_ref' => $this->workwearModel->model_ref,
                'name' => $this->workwearModel->name,
                'silhouette' => $this->workwearModel->silhouette,
                'colors' => $this->workwearModel->colors,
                'size_range' => $this->workwearModel->size_range,
                'status' => $this->workwearModel->status,
            ] : null),

            // Issuance progress: physical items of this model currently held by the employee.
            'issued_count' => $this->when(isset($this->resource->issued_count), fn () => (int) $this->resource->issued_count),
            'assigned_by_user' => $this->whenLoaded('assignedByUser', fn () => $this->assignedByUser
                ? ['id' => $this->assignedByUser->id, 'name' => $this->assignedByUser->name]
                : null),
        ];
    }
}
