<?php

namespace App\Modules\Procurement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Procurement\Models\RfqResponseLine
 */
class RfqResponseLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rfq_response_id' => $this->rfq_response_id,
            'rfq_line_id' => $this->rfq_line_id,
            'offered_unit_price_ht' => (float) $this->offered_unit_price_ht,
            'offered_qty' => (float) $this->offered_qty,
            'offered_lead_time_days' => (int) $this->offered_lead_time_days,
            'certifications_confirmed' => $this->certifications_confirmed,
            'vs_target_pct' => $this->vs_target_pct !== null ? (float) $this->vs_target_pct : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'rfq_line' => new RfqLineResource($this->whenLoaded('rfqLine')),
        ];
    }
}
