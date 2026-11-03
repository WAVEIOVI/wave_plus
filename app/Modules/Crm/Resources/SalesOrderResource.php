<?php

declare(strict_types=1);

namespace App\Modules\Crm\Resources;

use App\Modules\BusinessPartners\Resources\ClientResource;
use App\Modules\BusinessPartners\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Crm\Models\SalesOrder
 */
class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'so_type' => $this->so_type->value,
            'creation_path' => $this->creation_path instanceof \BackedEnum
                ? $this->creation_path->value
                : ($this->creation_path ?? 'manual'),
            'quotation_id' => $this->quotation_id,
            'contract_id' => $this->contract_id,
            'client_id' => $this->client_id,
            'client_po_reference' => $this->client_po_reference,
            'client_po_document_path' => $this->client_po_document_path,
            'requested_fulfillment_mode' => $this->requested_fulfillment_mode,
            'requested_delivery_date_from' => $this->requested_delivery_date_from?->toDateString(),
            'requested_delivery_date_to' => $this->requested_delivery_date_to?->toDateString(),
            'committed_delivery_date_from' => $this->committed_delivery_date_from?->toDateString(),
            'committed_delivery_date_to' => $this->committed_delivery_date_to?->toDateString(),
            'starter_locked_at' => $this->starter_locked_at?->toISOString(),
            'is_starter_locked' => $this->isStarterLocked(),
            'status' => $this->status->value,
            'fulfillment_status' => $this->calculateFulfillmentStatus(),
            'fulfillment' => $this->when(
                $this->relationLoaded('deliveryWorkOrders') || $this->relationLoaded('serviceWorkOrders'),
                fn () => app(\App\Modules\Crm\Services\SalesOrderFulfillmentService::class)
                    ->getFulfillmentSummary($this->resource)
            ),
            'currency' => $this->currency,
            'payment_terms' => $this->payment_terms instanceof \BackedEnum ? $this->payment_terms->value : $this->payment_terms,
            'price_list_id' => $this->price_list_id,
            'pricing_locked_at' => $this->pricing_locked_at?->toISOString(),
            'feasibility_status' => $this->feasibility_status->value,
            'credit_check_result' => $this->credit_check_result,
            'stock_check_result' => $this->stock_check_result,
            'capacity_check_result' => $this->capacity_check_result,
            'requires_secondary_approval' => (bool) $this->requires_secondary_approval,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'total_ht' => (float) $this->total_ht,
            'total_tva' => (float) $this->total_tva,
            'total_ttc' => (float) $this->total_ttc,
            'has_service_lines' => $this->isService(),
            'has_goods_lines' => $this->isSales(),
            'lsa_id' => $this->lsa_id,
            'gcs_id' => $this->gcs_id,
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'confirmed_by' => $this->confirmed_by,
            'internal_notes' => $this->internal_notes,
            'client_notes' => $this->client_notes,
            'parent_so_id' => $this->parent_so_id,
            'version_number' => $this->version_number,
            'is_current_version' => (bool) $this->is_current_version,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'client' => new ClientResource($this->whenLoaded('client')),
            'quotation' => new QuotationResource($this->whenLoaded('quotation')),
            'contract' => new ContractResource($this->whenLoaded('contract')),
            'confirmed_by_user' => new UserResource($this->whenLoaded('confirmedByUser')),
            'reviewed_by_user' => new UserResource($this->whenLoaded('reviewedByUser')),
            'lsa_document' => new LsaDocumentResource($this->whenLoaded('lsaDocument')),
            'gcs_document' => new GcsDocumentResource($this->whenLoaded('gcsDocument')),
            'lines' => SalesOrderLineResource::collection($this->whenLoaded('lines')),
            'locations' => $this->whenLoaded('locations'),
            'primary_location' => $this->whenLoaded('primaryLocation'),
            'delivery_work_orders' => $this->whenLoaded('deliveryWorkOrders'),
            'service_work_orders' => $this->whenLoaded('serviceWorkOrders'),
        ];
    }
}
