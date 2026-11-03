<?php

namespace App\Modules\Procurement\Resources;

use App\Modules\BusinessPartners\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Procurement\Models\InternalPurchaseRequest
 */
class IprResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'trigger_source' => $this->trigger_source,
            'trigger_reference' => $this->trigger_reference,
            'requested_by' => $this->requested_by,
            'assigned_to' => $this->assigned_to,
            'urgency' => $this->urgency,
            'needed_by_date' => $this->needed_by_date?->toDateString(),
            'status' => $this->status,
            'merged_into_ipr_id' => $this->merged_into_ipr_id,
            'converted_to_po_id' => $this->converted_to_po_id,
            'approval_note' => $this->approval_note,
            'rejection_reason' => $this->rejection_reason,
            'internal_justification' => $this->internal_justification,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Loaded Relationships
            'requested_by_user' => new UserResource($this->whenLoaded('requestedBy')),
            'assigned_to_user' => new UserResource($this->whenLoaded('assignedTo')),
            'lines' => IprLineResource::collection($this->whenLoaded('lines')),
            'po' => $this->whenLoaded('po'),
        ];
    }
}
