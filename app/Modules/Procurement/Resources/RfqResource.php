<?php

namespace App\Modules\Procurement\Resources;

use App\Modules\BusinessPartners\Resources\SupplierResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Procurement\Models\RfqHeader
 */
class RfqResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'ipr_ids' => $this->ipr_ids,
            'title' => $this->title,
            'response_deadline' => $this->response_deadline->toISOString(),
            'required_delivery_date' => $this->required_delivery_date?->toDateString(),
            'evaluation_criteria' => $this->evaluation_criteria,
            'terms_and_conditions' => $this->terms_and_conditions,
            'status' => $this->status,

            'awarded_to_supplier_id' => $this->awarded_to_supplier_id,
            'awarded_at' => $this->awarded_at?->toISOString(),
            'awarded_by' => $this->awarded_by,
            'awarded_by_user_name' => $this->whenLoaded('awardedByUser', fn () => $this->awardedByUser?->full_name ?? $this->awardedByUser?->username),
            'converted_to_po_id' => $this->converted_to_po_id,
            'iprs' => empty($this->ipr_ids) ? [] : \App\Modules\Procurement\Models\InternalPurchaseRequest::whereIn('id', $this->ipr_ids)->get(['id', 'reference']),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'lines' => RfqLineResource::collection($this->whenLoaded('lines')),
            'invitations' => RfqInvitationResource::collection($this->whenLoaded('invitations')),
            'responses' => RfqResponseResource::collection($this->whenLoaded('responses')),
            'awarded_to' => new SupplierResource($this->whenLoaded('awardedToSupplier')),
        ];
    }
}
