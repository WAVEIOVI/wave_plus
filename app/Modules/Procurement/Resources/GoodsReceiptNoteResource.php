<?php

namespace App\Modules\Procurement\Resources;

use App\Modules\BusinessPartners\Resources\SupplierResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Procurement\Models\GoodsReceiptNote
 */
class GoodsReceiptNoteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'po_id' => $this->po_id,
            'supplier_id' => $this->supplier_id,
            'received_by' => $this->received_by,
            'received_at' => $this->received_at->toISOString(),
            'supplier_delivery_note_ref' => $this->supplier_delivery_note_ref,
            'supplier_delivery_note_path' => $this->hasMedia('delivery_note')
                ? url("/api/procurement/grns/{$this->id}/download-delivery-note")
                : $this->supplier_delivery_note_path,
            'delivery_note_download_url' => $this->hasMedia('delivery_note')
                ? url("/api/procurement/grns/{$this->id}/download-delivery-note")
                : null,
            'status' => $this->status,
            'is_partial_delivery' => $this->is_partial_delivery,
            'overall_qc_result' => $this->overall_qc_result,
            'qc_performed_by' => $this->qc_performed_by,
            'qc_performed_at' => $this->qc_performed_at?->toISOString(),
            'delivery_on_time' => $this->delivery_on_time,
            'days_late' => $this->days_late,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'purchase_order' => new PurchaseOrderResource($this->whenLoaded('purchaseOrder')),
            'lines' => $this->whenLoaded('lines'),
        ];
    }
}
