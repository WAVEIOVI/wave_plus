<?php

declare(strict_types=1);

namespace App\Modules\Crm\Requests;

use App\Modules\Crm\Enums\PaymentTerms;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\SalesOrderType;
use App\Modules\Crm\Enums\SoCreationPath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'so_type' => ['required', 'string', Rule::enum(SalesOrderType::class)],
            'creation_path' => ['nullable', 'string', Rule::enum(SoCreationPath::class)],
            'quotation_id' => ['nullable', 'ulid', 'exists:quotations,id'],
            'contract_id' => ['nullable', 'ulid', 'exists:contracts,id'],
            'client_id' => ['required', 'ulid', 'exists:clients,id'],
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
            'payment_terms' => ['required', 'string', Rule::enum(PaymentTerms::class)],
            'internal_notes' => ['nullable', 'string'],
            'client_notes' => ['nullable', 'string'],

            'lines' => ['sometimes', 'array'],
            'lines.*.is_ad_hoc' => ['nullable', 'boolean'],
            'lines.*.ad_hoc_name' => ['nullable', 'required_if:lines.*.is_ad_hoc,true', 'string'],
            'lines.*.ad_hoc_sku' => ['nullable', 'string'],
            'lines.*.catalog_item_id' => ['nullable', 'required_if:lines.*.is_ad_hoc,false', 'string', 'exists:catalog_items,id'],
            'lines.*.variant_id' => ['nullable', 'string', 'exists:catalog_variants,id'],
            'lines.*.item_type_snapshot' => ['nullable', 'string'],
            'lines.*.qty' => ['required_with:lines', 'numeric', 'min:0.001'],
            'lines.*.unit_id' => ['nullable', 'string', 'exists:units_of_measure,id'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.total_ht' => ['nullable', 'numeric', 'min:0'],
            'lines.*.total_tva' => ['nullable', 'numeric', 'min:0'],
            'lines.*.total_ttc' => ['nullable', 'numeric', 'min:0'],
            'lines.*.target_site_id' => ['nullable'],
            'lines.*.target_equipment_ids' => ['nullable', 'array'],
            'lines.*.delivery_date' => ['nullable', 'date'],

            'locations' => ['nullable', 'array'],
            'locations.*.bp_address_id' => ['required_with:locations', 'ulid', 'exists:bp_addresses,id'],
            'locations.*.client_site_id' => ['nullable', 'ulid', 'exists:client_sites,id'],
            'locations.*.purpose' => ['nullable', 'string', 'in:delivery,service,billing'],
            'locations.*.is_primary' => ['nullable', 'boolean'],
            'locations.*.metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $soType = $this->input('so_type');
            $clientId = $this->input('client_id');
            $creationPath = $this->input('creation_path');

            $isContractCallOff = $creationPath === SoCreationPath::ContractCallOff->value;

            if ($isContractCallOff && empty($this->input('contract_id'))) {
                $v->errors()->add('contract_id', 'Contract ID is required for contract call-off creation path.');
            }

            $isQuotation = $creationPath === SoCreationPath::Quotation->value;

            if ($isQuotation && empty($this->input('quotation_id'))) {
                $v->errors()->add('quotation_id', 'Quotation ID is required for quotation creation path.');
            }

            // Cross-validation: quotation belongs to client
            if (! empty($this->input('quotation_id')) && ! empty($clientId)) {
                $quote = \App\Modules\Crm\Models\Quotation::find($this->input('quotation_id'));
                if ($quote && $quote->client_id !== $clientId) {
                    $v->errors()->add('quotation_id', 'The selected quotation does not belong to this client.');
                }
            }

            // Cross-validation: contract belongs to client
            if (! empty($this->input('contract_id')) && ! empty($clientId)) {
                $contract = \App\Modules\Crm\Models\Contract::find($this->input('contract_id'));
                if ($contract && $contract->client_id !== $clientId) {
                    $v->errors()->add('contract_id', 'The selected contract does not belong to this client.');
                }
            }

            // Cross-validation: fulfillment mode valid for so_type
            $fulfillmentMode = $this->input('requested_fulfillment_mode');
            if (! empty($fulfillmentMode) && ! empty($soType)) {
                if (! \App\Modules\Crm\Enums\SoFulfillmentMode::isValidForSoType($fulfillmentMode, $soType)) {
                    $v->errors()->add('requested_fulfillment_mode', "Fulfillment mode '{$fulfillmentMode}' is not valid for {$soType} orders.");
                }
            }

            $lines = $this->input('lines', []);
            foreach ($lines as $idx => $line) {
                $snapshot = $line['item_type_snapshot'] ?? null;
                if ($soType === 'sales' && in_array($snapshot, ['service', 'eq_servicing'], true)) {
                    $v->errors()->add("lines.{$idx}.item_type_snapshot", 'Cannot add service items to a Sales-type Sales Order.');
                }
                if ($soType === 'service' && $snapshot !== null && ! in_array($snapshot, ['service', 'eq_servicing', 'spare_part', 'consumable'], true)) {
                    $v->errors()->add("lines.{$idx}.item_type_snapshot", 'Cannot add physical products to a Service-type Sales Order.');
                }
            }
        });
    }
}
