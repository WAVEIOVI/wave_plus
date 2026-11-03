<?php

declare(strict_types=1);

namespace App\Modules\Crm\Resources;

use App\Modules\BusinessPartners\Resources\ClientResource;
use App\Modules\BusinessPartners\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Crm\Models\Quotation
 */
class QuotationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'version' => $this->version,
            'parent_quotation_id' => $this->parent_quotation_id,
            'pr_id' => $this->pr_id,
            'client_id' => $this->client_id,
            'site_id' => $this->site_id,
            'contract_id' => $this->contract_id,
            'valid_until' => $this->valid_until->toDateString(),
            'currency' => $this->currency,
            'payment_terms' => $this->payment_terms instanceof \BackedEnum ? $this->payment_terms->value : $this->payment_terms,
            'global_discount_pct' => (float) $this->global_discount_pct,
            'total_ht' => (float) $this->total_ht,
            'total_tva' => (float) $this->total_tva,
            'total_ttc' => (float) $this->total_ttc,
            'approval_required' => $this->approval_required,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'converted_to_so_id' => $this->converted_to_so_id,
            'rejection_reason' => $this->rejection_reason,
            'cover_letter' => $this->cover_letter,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'client' => new ClientResource($this->whenLoaded('client')),
            'price_request' => new PriceRequestResource($this->whenLoaded('priceRequest')),
            'contract' => new ContractResource($this->whenLoaded('contract')),
            'approved_by_user' => new UserResource($this->whenLoaded('approvedByUser')),
            'sales_order' => new SalesOrderResource($this->whenLoaded('convertedToSalesOrder')),
            'lines' => QuotationLineResource::collection($this->whenLoaded('lines')),
            'revisions' => QuotationResource::collection($this->whenLoaded('revisions')),
        ];
    }
}
