<?php

namespace App\Modules\Procurement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRfqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Header ───────────────────────────────────────────────────
            'title' => ['required', 'string', 'max:200'],
            'response_deadline' => ['required', 'date', 'after:now'],
            'required_delivery_date' => ['nullable', 'date'],
            'evaluation_criteria' => ['nullable', 'array'],
            'evaluation_criteria.price_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'evaluation_criteria.lead_time_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'evaluation_criteria.quality_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'evaluation_criteria.technical_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'evaluation_criteria.payment_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'terms_and_conditions' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,sent,awaiting_responses,under_evaluation,awarded,cancelled'],
            'awarded_to_supplier_id' => ['ulid', 'nullable', 'exists:suppliers,id'],
            'awarded_at' => ['nullable', 'date'],
            'awarded_by' => ['nullable', 'exists:users,id'],
            'ipr_ids' => ['nullable', 'array'],
            'ipr_ids.*' => ['ulid', 'exists:internal_purchase_requests,id'],

            // ── Lines ────────────────────────────────────────────────────
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.is_ad_hoc' => ['sometimes', 'boolean'],

            // Catalog item: required when NOT ad-hoc
            'lines.*.catalog_item_id' => [
                Rule::requiredIf(function () {
                    // Per-line logic handled in withValidator
                    return false;
                }),
                'nullable', 'string', 'exists:catalog_items,id',
            ],
            'lines.*.variant_id' => ['nullable', 'string', 'exists:catalog_variants,id'],

            // Ad-hoc fields
            'lines.*.ad_hoc_name' => ['nullable', 'string', 'max:255'],
            'lines.*.ad_hoc_sku' => ['nullable', 'string', 'max:100'],

            'lines.*.description_for_supplier' => ['nullable', 'string', 'max:300'],
            'lines.*.qty_requested' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_id' => ['nullable', 'string', 'exists:units_of_measure,id'],
            'lines.*.target_price_ht' => ['nullable', 'numeric', 'min:0'],
            'lines.*.required_certifications' => ['nullable', 'array'],

            // ── Invitations ──────────────────────────────────────────────
            'invitations' => ['nullable', 'array'],
            'invitations.*.supplier_id' => ['required', 'exists:suppliers,id'],
        ];
    }

    /**
     * Configure the validator instance — per-line conditional validation.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $lines = $this->input('lines', []);

            foreach ($lines as $index => $line) {
                $isAdHoc = filter_var($line['is_ad_hoc'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($isAdHoc) {
                    if (empty($line['ad_hoc_name'])) {
                        $validator->errors()->add(
                            "lines.{$index}.ad_hoc_name",
                            'The ad-hoc item name is required for custom items.'
                        );
                    }
                } else {
                    if (empty($line['catalog_item_id'])) {
                        $validator->errors()->add(
                            "lines.{$index}.catalog_item_id",
                            'A catalog item must be selected for non ad-hoc lines.'
                        );
                    }
                }
            }

            // Validate evaluation criteria weights sum to 100 if provided
            $criteria = $this->input('evaluation_criteria');
            if ($criteria && (isset($criteria['price_weight']) || isset($criteria['lead_time_weight']) || isset($criteria['quality_weight']) || isset($criteria['technical_weight']) || isset($criteria['payment_weight']))) {
                $total = (int) ($criteria['price_weight'] ?? 0)
                       + (int) ($criteria['lead_time_weight'] ?? 0)
                       + (int) ($criteria['quality_weight'] ?? 0)
                       + (int) ($criteria['technical_weight'] ?? 0)
                       + (int) ($criteria['payment_weight'] ?? 0);

                if ($total !== 100) {
                    $validator->errors()->add(
                        'evaluation_criteria',
                        'Evaluation criteria weights must sum to 100.'
                    );
                }
            }
        });
    }
}
