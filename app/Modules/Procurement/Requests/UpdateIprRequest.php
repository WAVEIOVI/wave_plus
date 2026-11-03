<?php

namespace App\Modules\Procurement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIprRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trigger_source' => ['required', 'string', 'in:auto_stock_alert,auto_workshop,manual_user,planned_project'],
            'trigger_reference' => ['nullable', 'string', 'max:255'],
            'requested_by' => ['required', 'exists:users,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'urgency' => ['required', 'string', 'in:low,normal,urgent,critical'],
            'needed_by_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:draft,pending_approval,approved,rejected,merged,converted_to_po,cancelled'],
            'approval_note' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],
            'internal_justification' => ['nullable', 'string'],

            // Lines
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'string', 'exists:ipr_lines,id'],
            'lines.*.is_ad_hoc' => ['sometimes', 'boolean'],

            // Catalog item: required when NOT ad-hoc, nullable when ad-hoc
            'lines.*.catalog_item_id' => [
                Rule::requiredIf(function () {
                    return false;
                }),
                'nullable',
                'string',
                'exists:catalog_items,id',
            ],
            'lines.*.variant_id' => [
                'nullable',
                'string',
                'exists:catalog_variants,id',
            ],

            // Ad-hoc fields: required when ad-hoc
            'lines.*.ad_hoc_name' => ['nullable', 'string', 'max:255'],
            'lines.*.ad_hoc_sku' => ['nullable', 'string', 'max:100'],

            'lines.*.qty_requested' => ['required', 'numeric', 'min:0.01'],
            'lines.*.qty_current_stock' => ['nullable', 'numeric', 'min:0'],
            'lines.*.suggested_supplier_id' => ['nullable', 'exists:suppliers,id'],
            'lines.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0'],
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
                    // Ad-hoc line: ad_hoc_name is required
                    if (empty($line['ad_hoc_name'])) {
                        $validator->errors()->add(
                            "lines.{$index}.ad_hoc_name",
                            'The ad-hoc item name is required for custom items.'
                        );
                    }
                } else {
                    // Catalog line: catalog_item_id is required
                    if (empty($line['catalog_item_id'])) {
                        $validator->errors()->add(
                            "lines.{$index}.catalog_item_id",
                            'A catalog item must be selected for non ad-hoc lines.'
                        );
                    }
                }
            }
        });
    }
}
