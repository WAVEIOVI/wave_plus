<?php

namespace App\Modules\ClientAssets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkwearAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'employee_id' => $this->employee_id,
            'assigned_by' => $this->assigned_by,
            'assigned_at' => $this->assigned_at?->toISOString(),
            'returned_at' => $this->returned_at?->toISOString(),
            'condition_at_assign' => $this->condition_at_assign,
            'condition_at_return' => $this->condition_at_return,
            'return_action' => $this->return_action,
            'returned_by' => $this->returned_by,
            'notes' => $this->notes,

            'item' => $this->whenLoaded('item', fn () => [
                'id' => $this->item->id,
                'item_code' => $this->item->item_code,
                'garment_type' => $this->item->garment_type,
                'size' => $this->item->size,
                'model_name' => $this->item->relationLoaded('workwearModel') ? $this->item->workwearModel?->name : null,
                'status' => is_object($this->item->status) ? $this->item->status->value : $this->item->status,
            ]),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_ref' => $this->employee->employee_ref,
            ]),
            'assigned_by_user' => $this->whenLoaded('assignedByUser', fn () => ['id' => $this->assignedByUser->id, 'name' => $this->assignedByUser->name]),
            'returned_by_user' => $this->whenLoaded('returnedByUser', fn () => ['id' => $this->returnedByUser->id, 'name' => $this->returnedByUser->name]),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
