<?php

namespace App\Modules\Procurement\Resources;

use App\Modules\BusinessPartners\Resources\SupplierResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Procurement\Models\RfqInvitation
 */
class RfqInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rfq_id' => $this->rfq_id,
            'supplier_id' => $this->supplier_id,
            'sent_at' => $this->sent_at?->toISOString(),
            'viewed_at' => $this->viewed_at?->toISOString(),
            'status' => $this->status,
            'decline_reason' => $this->decline_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'response' => new RfqResponseResource($this->whenLoaded('response')),
        ];
    }
}
