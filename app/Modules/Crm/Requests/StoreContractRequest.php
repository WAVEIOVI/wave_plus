<?php

declare(strict_types=1);

namespace App\Modules\Crm\Requests;

use App\Modules\Crm\Enums\ContractStatus;
use App\Modules\Crm\Enums\ContractType;
use App\Modules\Crm\Enums\PaymentTerms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(ContractType::class)],
            'client_id' => ['required', 'ulid', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'term_model' => ['nullable', 'string', 'in:fixed_term,evergreen'],
            'scope_covers' => ['nullable', 'array'],
            'provider_representative_id' => ['nullable', 'ulid', 'exists:users,id'],
            'provider_representative_name' => ['nullable', 'string', 'max:255'],
            'provider_representative_title' => ['nullable', 'string', 'max:255'],
            'client_representative_id' => ['nullable', 'ulid', 'exists:bp_contacts,id'],
            'client_representative_name' => ['nullable', 'string', 'max:255'],
            'client_representative_title' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'auto_renewal' => ['boolean'],
            'renewal_notice_days' => ['nullable', 'integer', 'min:1'],
            'renewal_duration_months' => ['nullable', 'integer', 'min:1'],
            'termination_notice_days' => ['nullable', 'integer', 'min:1'],

            'contract_value_ht' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'payment_terms' => ['nullable', 'string', Rule::enum(PaymentTerms::class)],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'late_payment_penalty_rate' => ['nullable', 'numeric', 'min:0'],
            'volume_commitment_value' => ['nullable', 'numeric', 'min:0'],
            'min_order_value' => ['nullable', 'numeric', 'min:0'],
            'commitment_type' => ['nullable', 'string', 'in:none,quantity,value'],

            'price_list_id' => ['nullable', 'ulid', 'exists:price_lists,id'],
            'sla_response_hours' => ['nullable', 'integer', 'min:1'],
            'sla_resolution_hours' => ['nullable', 'integer', 'min:1'],
            'sla_penalty_per_hour_ht' => ['nullable', 'numeric', 'min:0'],
            'coverage_sites' => ['nullable', 'array'],
            'covered_equipment_types' => ['nullable', 'array'],

            'volume_commitment_qty' => ['nullable', 'numeric', 'min:0'],
            'min_call_off_qty' => ['nullable', 'numeric', 'min:0'],
            'price_revision_applicable' => ['boolean'],
            'price_revision_clause' => ['nullable', 'string'],
            'price_revision_frequency' => ['nullable', 'string', 'max:50'],
            'standard_lead_time_days' => ['nullable', 'integer', 'min:1'],
            'delivery_location_default' => ['nullable', 'string'],
            'packaging_requirements' => ['nullable', 'string'],
            'product_warranty_period_months' => ['nullable', 'integer', 'min:0'],
            'product_conformity_standards' => ['nullable', 'string'],
            'nonconformity_return_window_days' => ['nullable', 'integer', 'min:0'],
            'covered_catalog_categories' => ['nullable', 'array'],

            'scope_description' => ['nullable', 'string'],
            'authorized_requesters' => ['nullable', 'array'],
            'feasibility_review_sla_days' => ['nullable', 'integer', 'min:0'],

            'governing_law' => ['nullable', 'string', 'max:255'],
            'dispute_resolution_method' => ['nullable', 'string', 'max:255'],
            'dispute_forum' => ['nullable', 'string', 'max:255'],
            'arbitration_body' => ['nullable', 'string', 'max:255'],
            'liability_capped' => ['boolean'],
            'liability_cap_value' => ['nullable', 'numeric', 'min:0'],
            'liability_cap_basis' => ['nullable', 'string', 'max:255'],
            'confidentiality_applies' => ['boolean'],
            'confidentiality_survival_years' => ['nullable', 'integer', 'min:0'],
            'exclusivity_applies' => ['boolean'],
            'exclusivity_scope' => ['nullable', 'string', 'max:255'],
            'exclusivity_notes' => ['nullable', 'string'],
            'insurance_required' => ['boolean'],
            'insurance_minimum_coverage' => ['nullable', 'numeric', 'min:0'],
            'insurance_policy_reference' => ['nullable', 'string', 'max:255'],
            'cure_period_days' => ['nullable', 'integer', 'min:0'],
            'negotiation_period_days' => ['nullable', 'integer', 'min:0'],

            'parent_contract_id' => ['nullable', 'ulid', 'exists:contracts,id'],
            'supersedes_contract_id' => ['nullable', 'ulid', 'exists:contracts,id'],
            'amendment_reason' => ['nullable', 'string'],
            'originating_quotation_id' => ['nullable', 'ulid', 'exists:quotations,id'],
            'internal_notes' => ['nullable', 'string'],

            'status' => ['nullable', 'string', Rule::enum(ContractStatus::class)],
            'signed_by_client' => ['nullable', 'string', 'max:255'],
            'document_path' => ['nullable', 'string', 'max:255'],

            'lines' => ['nullable', 'array'],
            'lines.*.is_ad_hoc' => ['nullable', 'boolean'],
            'lines.*.catalog_item_id' => ['nullable', 'string', 'exists:catalog_items,id', 'required_unless:lines.*.is_ad_hoc,true'],
            'lines.*.ad_hoc_name' => ['nullable', 'string', 'max:255', 'required_if:lines.*.is_ad_hoc,true'],
            'lines.*.ad_hoc_sku' => ['nullable', 'string', 'max:100'],
            'lines.*.contracted_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.contracted_unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.called_off_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.remaining_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Auto-renewal constraint
            if ($this->boolean('auto_renewal') && empty($this->renewal_notice_days)) {
                $validator->errors()->add('renewal_notice_days', 'Renewal notice period is required when auto-renewal is enabled.');
            }

            // Liability cap constraint
            if ($this->boolean('liability_capped') && empty($this->liability_cap_value)) {
                $validator->errors()->add('liability_cap_value', 'Liability cap value is required when liability is capped.');
            }
        });
    }
}
