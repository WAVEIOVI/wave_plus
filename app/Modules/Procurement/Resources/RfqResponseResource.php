<?php

namespace App\Modules\Procurement\Resources;

use App\Modules\BusinessPartners\Resources\SupplierResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Procurement\Models\RfqResponse
 */
class RfqResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rfq_id' => $this->rfq_id,
            'supplier_id' => $this->supplier_id,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'validity_until' => $this->validity_until?->toDateString(),
            'can_deliver_by' => $this->can_deliver_by?->toDateString(),
            'payment_terms_offered' => $this->payment_terms_offered,
            'total_ht' => (float) $this->total_ht,
            'evaluation_score' => $this->evaluation_score !== null ? (float) $this->evaluation_score : null,
            'evaluation_details' => $this->evaluation_details,
            'is_awarded' => (bool) $this->is_awarded,
            'supplier_notes' => $this->supplier_notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'rfq' => new RfqResource($this->whenLoaded('rfq')),
            'lines' => RfqResponseLineResource::collection($this->whenLoaded('responseLines')),
        ];
    }
}
