<?php

declare(strict_types=1);

namespace App\Modules\Crm\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Crm\Models\GcsDocument
 */
class GcsDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'sales_order_id' => $this->sales_order_id,
            'contract_id' => $this->contract_id,
            'status' => $this->status,
            'committed_delivery_date' => $this->committed_delivery_date?->toDateString(),
            'goods_lines' => $this->goods_lines,
            'delivery_address_snapshot' => $this->delivery_address_snapshot,
            'total_ht' => (float) $this->total_ht,
            'total_tva' => (float) $this->total_tva,
            'total_ttc' => (float) $this->total_ttc,
            'payment_terms' => $this->payment_terms,
            'incoterms' => $this->incoterms,
            'special_delivery_instructions' => $this->special_delivery_instructions,
            'client_signed_at' => $this->client_signed_at?->toISOString(),
            'client_signatory' => $this->client_signatory,
            'delivery_note_id' => $this->delivery_note_id,
            'document_path' => $this->document_path,
            'signed_document_path' => $this->signed_document_path,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'sales_order' => new SalesOrderResource($this->whenLoaded('salesOrder')),
            'contract' => new ContractResource($this->whenLoaded('contract')),
        ];
    }
}
