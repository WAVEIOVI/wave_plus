<?php

namespace App\Modules\Procurement\Resources;

use App\Modules\BusinessPartners\Resources\SupplierResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Procurement\Models\PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'creation_path' => $this->creation_path,
            'rfq_id' => $this->rfq_id,
            'ipr_ids' => $this->ipr_ids,
            'supplier_id' => $this->supplier_id,
            'site_id' => $this->site_id,
            'supplier_contact_id' => $this->supplier_contact_id,
            'supplier_address_id' => $this->supplier_address_id,
            'currency' => $this->currency,
            'exchange_rate' => (float) $this->exchange_rate,
            'payment_terms' => $this->payment_terms,
            'incoterms' => $this->incoterms,
            'delivery_address' => $this->delivery_address,
            'committed_delivery_date' => $this->committed_delivery_date?->toDateString(),
            'total_ht' => (float) $this->total_ht,
            'total_tax' => (float) $this->total_tax,
            'total_ttc' => (float) $this->total_ttc,
            'approval_required' => $this->approval_required,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toISOString(),
            'status' => $this->status,
            'sent_at' => $this->sent_at?->toISOString(),
            'acknowledged_at' => $this->acknowledged_at?->toISOString(),
            'supplier_po_reference' => $this->supplier_po_reference,
            'notes_to_supplier' => $this->notes_to_supplier,
            'internal_notes' => $this->internal_notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'site' => $this->whenLoaded('site'),
            'lines' => $this->whenLoaded('lines'),
            'goods_receipt_notes' => GoodsReceiptNoteResource::collection($this->whenLoaded('goodsReceiptNotes')),
        ];
    }
}
