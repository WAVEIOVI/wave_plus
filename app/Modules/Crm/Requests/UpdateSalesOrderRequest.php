<?php

declare(strict_types=1);

namespace App\Modules\Crm\Requests;

use App\Modules\Crm\Enums\PaymentTerms;
use App\Modules\Crm\Enums\SalesOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['sometimes', 'required', 'ulid', 'exists:clients,id'],
            'creation_path' => ['nullable', 'string', Rule::enum(\App\Modules\Crm\Enums\SoCreationPath::class)],
            'locations' => ['nullable', 'array'],
            'locations.*.bp_address_id' => ['required_with:locations', 'ulid', 'exists:bp_addresses,id'],
            'locations.*.client_site_id' => ['nullable', 'string', 'exists:client_sites,id'],
            'locations.*.purpose' => ['nullable', 'string', 'in:delivery,service,billing'],
            'locations.*.is_primary' => ['nullable', 'boolean'],
            'locations.*.metadata' => ['nullable', 'array'],
            'price_list_id' => ['nullable', 'ulid', 'exists:price_lists,id'],
            'client_po_reference' => ['nullable', 'string', 'max:100'],
            'client_po_document_path' => ['nullable', 'string', 'max:255'],
            'requested_fulfillment_mode' => ['nullable', 'string', Rule::enum(\App\Modules\Crm\Enums\SoFulfillmentMode::class)],
            'requested_delivery_date_from' => ['nullable', 'date'],
            'requested_delivery_date_to' => ['nullable', 'date', 'after_or_equal:requested_delivery_date_from'],
            'committed_delivery_date_from' => ['nullable', 'date'],
            'committed_delivery_date_to' => ['nullable', 'date', 'after_or_equal:committed_delivery_date_from'],
            'status' => ['nullable', 'string', Rule::in([
                SalesOrderStatus::Draft->value,
                SalesOrderStatus::Submitted->value,
                SalesOrderStatus::UnderReview->value,
                SalesOrderStatus::Accepted->value,
                SalesOrderStatus::Rejected->value,
                SalesOrderStatus::Cancelled->value,
                SalesOrderStatus::Completed->value,
                SalesOrderStatus::Closed->value,
            ])],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_terms' => ['sometimes', 'required', 'string', Rule::enum(PaymentTerms::class)],
            'internal_notes' => ['nullable', 'string'],
            'client_notes' => ['nullable', 'string'],

            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'string'],
            'lines.*.is_ad_hoc' => ['nullable', 'boolean'],
            'lines.*.ad_hoc_name' => ['nullable', 'string'],
            'lines.*.ad_hoc_sku' => ['nullable', 'string'],
            'lines.*.catalog_item_id' => ['nullable', 'string', 'exists:catalog_items,id'],
            'lines.*.variant_id' => ['nullable', 'string', 'exists:catalog_variants,id'],
            'lines.*.item_type_snapshot' => ['nullable', 'string'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_id' => ['nullable', 'string', 'exists:units_of_measure,id'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.total_ht' => ['nullable', 'numeric', 'min:0'],
            'lines.*.total_tva' => ['nullable', 'numeric', 'min:0'],
            'lines.*.total_ttc' => ['nullable', 'numeric', 'min:0'],
            'lines.*.target_site_id' => ['nullable'],
            'lines.*.target_equipment_ids' => ['nullable', 'array'],
            'lines.*.delivery_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v) {
            /** @var \App\Modules\Crm\Models\SalesOrder|null $so */
            $so = $this->route('salesOrder');

            if ($so && $so->isStarterLocked()) {
                $lockedFields = [
                    'client_id',
                    'so_type',
                    'creation_path',
                    'quotation_id',
                    'contract_id',
                    'requested_fulfillment_mode',
                    'requested_delivery_date_from',
                    'requested_delivery_date_to',
                    'committed_delivery_date_from',
                    'committed_delivery_date_to',
                ];

                foreach ($lockedFields as $field) {
                    if ($this->has($field)) {
                        $currentVal = $so->$field;
                        if ($currentVal instanceof \BackedEnum) {
                            $currentVal = $currentVal->value;
                        } elseif ($currentVal instanceof \Carbon\CarbonInterface) {
                            $currentVal = $currentVal->toDateString();
                        }

                        $newVal = $this->input($field);
                        if ($newVal instanceof \BackedEnum) {
                            $newVal = $newVal->value;
                        } elseif ($newVal instanceof \Carbon\CarbonInterface) {
                            $newVal = $newVal->toDateString();
                        } elseif (is_string($newVal) && str_contains($field, 'date')) {
                            $newVal = substr($newVal, 0, 10);
                        }

                        if ((string) ($currentVal ?? '') !== (string) ($newVal ?? '')) {
                            $v->errors()->add($field, "Cannot modify starter field '{$field}' after initial save. Create an amendment to change order foundation.");
                        }
                    }
                }
            }

            // Disallow manual attachment of LSA or GCS
            if ($this->has('lsa_id')) {
                $v->errors()->add('lsa_id', 'LSA document cannot be manually attached.');
            }
            if ($this->has('gcs_id')) {
                $v->errors()->add('gcs_id', 'GCS document cannot be manually attached.');
            }
        });
    }
}
