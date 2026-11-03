<?php

declare(strict_types=1);

namespace App\Modules\Crm\Resources;

use App\Modules\BusinessPartners\Resources\ClientResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Crm\Models\Contract
 */
class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type,
            'client_id' => $this->client_id,
            'title' => $this->title,
            'term_model' => $this->term_model ?: 'fixed_term',
            'scope_covers' => $this->scope_covers,
            'provider_representative_id' => $this->provider_representative_id,
            'provider_representative_name' => $this->provider_representative_name,
            'provider_representative_title' => $this->provider_representative_title,
            'client_representative_id' => $this->client_representative_id,
            'client_representative_name' => $this->client_representative_name,
            'client_representative_title' => $this->client_representative_title,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'auto_renewal' => $this->auto_renewal,
            'renewal_notice_days' => $this->renewal_notice_days,
            'renewal_duration_months' => $this->renewal_duration_months,
            'version' => $this->version,
            'parent_contract_id' => $this->parent_contract_id,
            'is_current_version' => $this->is_current_version,
            'supersedes_contract_id' => $this->supersedes_contract_id,
            'amendment_reason' => $this->amendment_reason,

            'termination_notice_days' => $this->termination_notice_days,
            'contract_value_ht' => (float) $this->contract_value_ht,
            'currency' => $this->currency,
            'payment_terms' => $this->payment_terms,
            'payment_method' => $this->payment_method,
            'credit_limit' => (float) $this->credit_limit,
            'late_payment_penalty_rate' => (float) $this->late_payment_penalty_rate,
            'volume_commitment_value' => (float) $this->volume_commitment_value,
            'min_order_value' => (float) $this->min_order_value,
            'commitment_type' => $this->commitment_type,

            'price_list_id' => $this->price_list_id,
            'sla_response_hours' => $this->sla_response_hours,
            'sla_resolution_hours' => $this->sla_resolution_hours,
            'sla_penalty_per_hour_ht' => (float) $this->sla_penalty_per_hour_ht,
            'coverage_sites' => $this->coverage_sites,
            'covered_equipment_types' => $this->covered_equipment_types,

            'volume_commitment_qty' => (float) $this->volume_commitment_qty,
            'min_call_off_qty' => (float) $this->min_call_off_qty,
            'price_revision_applicable' => (bool) $this->price_revision_applicable,
            'price_revision_clause' => $this->price_revision_clause,
            'price_revision_frequency' => $this->price_revision_frequency,
            'standard_lead_time_days' => $this->standard_lead_time_days,
            'delivery_location_default' => $this->delivery_location_default,
            'packaging_requirements' => $this->packaging_requirements,
            'product_warranty_period_months' => $this->product_warranty_period_months,
            'product_conformity_standards' => $this->product_conformity_standards,
            'nonconformity_return_window_days' => $this->nonconformity_return_window_days,
            'covered_catalog_categories' => $this->covered_catalog_categories,

            'scope_description' => $this->scope_description,
            'authorized_requesters' => $this->authorized_requesters,
            'feasibility_review_sla_days' => $this->feasibility_review_sla_days,

            'governing_law' => $this->governing_law,
            'dispute_resolution_method' => $this->dispute_resolution_method,
            'dispute_forum' => $this->dispute_forum,
            'arbitration_body' => $this->arbitration_body,
            'liability_capped' => (bool) $this->liability_capped,
            'liability_cap_value' => (float) $this->liability_cap_value,
            'liability_cap_basis' => $this->liability_cap_basis,
            'confidentiality_applies' => (bool) $this->confidentiality_applies,
            'confidentiality_survival_years' => $this->confidentiality_survival_years,
            'exclusivity_applies' => (bool) $this->exclusivity_applies,
            'exclusivity_scope' => $this->exclusivity_scope,
            'exclusivity_notes' => $this->exclusivity_notes,
            'insurance_required' => (bool) $this->insurance_required,
            'insurance_minimum_coverage' => (float) $this->insurance_minimum_coverage,
            'insurance_policy_reference' => $this->insurance_policy_reference,
            'cure_period_days' => $this->cure_period_days,
            'negotiation_period_days' => $this->negotiation_period_days,

            'originating_quotation_id' => $this->originating_quotation_id,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'internal_notes' => $this->internal_notes,

            'status' => $this->status,
            'renewal_status' => $this->renewal_status,
            'renewed_from_id' => $this->renewed_from_id,
            'signed_at' => $this->signed_at?->toISOString(),
            'signed_by_client' => $this->signed_by_client,
            'document_path' => $this->document_path,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'client' => new ClientResource($this->whenLoaded('client')),
            'client_representative' => $this->whenLoaded('clientRepresentative'),
            'provider_representative' => $this->whenLoaded('providerRepresentative'),
            'price_list' => new PriceListResource($this->whenLoaded('priceList')),
            'lines' => ContractLineResource::collection($this->whenLoaded('lines')),
            'sales_orders' => SalesOrderResource::collection($this->whenLoaded('salesOrders')),
        ];
    }
}
