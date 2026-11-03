<?php

namespace App\Modules\ClientAssets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkwearEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'employee_ref' => $this->employee_ref,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'department' => $this->department,
            'role' => $this->role,
            'position' => $this->position,
            'status' => $this->status,
            'sizes' => $this->sizes,
            'measurements' => $this->measurements,
            'workwear_requirements' => $this->workwear_requirements,
            'notes' => $this->notes,

            'client' => $this->whenLoaded('client', fn () => ['id' => $this->client->id, 'legal_name' => $this->client->legal_name]),
            'assignments' => WorkwearAssignmentResource::collection($this->whenLoaded('assignments')),
            'active_assignments' => WorkwearAssignmentResource::collection($this->whenLoaded('activeAssignments')),

            'model_assignments' => WorkwearEmployeeModelResource::collection($this->whenLoaded('modelAssignments')),
            'active_model_assignments_count' => $this->whenCounted('activeModelAssignments'),
            'assignments_count' => $this->whenCounted('assignments'),
            'active_assignments_count' => $this->whenCounted('activeAssignments'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
