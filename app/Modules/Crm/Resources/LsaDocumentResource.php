<?php

declare(strict_types=1);

namespace App\Modules\Crm\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Crm\Models\LsaDocument
 */
class LsaDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'sales_order_id' => $this->sales_order_id,
            'contract_id' => $this->contract_id,
            'status' => $this->status,
            'sla_response_hours' => $this->sla_response_hours,
            'sla_resolution_hours' => $this->sla_resolution_hours,
            'scheduled_start_date' => $this->scheduled_start_date?->toDateString(),
            'scheduled_end_date' => $this->scheduled_end_date?->toDateString(),
            'assigned_team' => $this->assigned_team,
            'equipment_scope' => $this->equipment_scope,
            'service_scope' => $this->service_scope,
            'client_signed_at' => $this->client_signed_at?->toISOString(),
            'client_signatory' => $this->client_signatory,
            'client_signature_path' => $this->client_signature_path,
            'document_path' => $this->document_path,
            'signed_document_path' => $this->signed_document_path,
            'completion_report_path' => $this->completion_report_path,
            'client_acknowledgement_at' => $this->client_acknowledgement_at?->toISOString(),
            'special_terms' => $this->special_terms,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'sales_order' => new SalesOrderResource($this->whenLoaded('salesOrder')),
            'contract' => new ContractResource($this->whenLoaded('contract')),
        ];
    }
}
