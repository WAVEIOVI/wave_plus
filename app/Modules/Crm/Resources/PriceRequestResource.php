<?php

declare(strict_types=1);

namespace App\Modules\Crm\Resources;

use App\Modules\BusinessPartners\Resources\ClientResource;
use App\Modules\BusinessPartners\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Crm\Models\PriceRequest
 */
class PriceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'client_id' => $this->client_id,
            'source' => $this->source,
            'received_at' => $this->received_at->toISOString(),
            'response_deadline' => $this->response_deadline?->toDateString(),
            'client_reference' => $this->client_reference,
            'site_id' => $this->site_id,
            'assigned_to' => $this->assigned_to,
            'status' => $this->status,
            'client_note' => $this->client_note,
            'internal_note' => $this->internal_note,
            'converted_to_quotation_id' => $this->converted_to_quotation_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'client' => new ClientResource($this->whenLoaded('client')),
            'assigned_to_user' => new UserResource($this->whenLoaded('assignedToUser')),
            'lines' => PriceRequestLineResource::collection($this->whenLoaded('lines')),
            'quotation' => new QuotationResource($this->whenLoaded('convertedToQuotation')),
        ];
    }
}
