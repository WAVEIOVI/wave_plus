<?php

declare(strict_types=1);

namespace App\Modules\Crm\Requests;

use App\Modules\Crm\Enums\ItemTypeSnapshot;
use App\Modules\Crm\Enums\PaymentTerms;
use App\Modules\Crm\Enums\QuotationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['nullable', 'integer', 'min:1'],
            'parent_quotation_id' => ['nullable', 'ulid', 'exists:quotations,id'],
            'pr_id' => ['nullable', 'ulid', 'exists:price_requests,id'],
            'client_id' => ['required', 'ulid', 'exists:clients,id'],
            'site_id' => ['nullable', 'ulid'],
            'contract_id' => ['nullable', 'ulid', 'exists:contracts,id'],
            'valid_until' => ['required', 'date', 'after:today'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_terms' => ['required', 'string', Rule::enum(PaymentTerms::class)],
            'global_discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'approval_required' => ['boolean'],
            'cover_letter' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::enum(QuotationStatus::class)],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.is_ad_hoc' => ['nullable', 'boolean'],
            'lines.*.catalog_item_id' => ['nullable', 'string', 'exists:catalog_items,id', 'required_unless:lines.*.is_ad_hoc,true'],
            'lines.*.variant_id' => ['nullable', 'string', 'exists:catalog_variants,id'],
            'lines.*.ad_hoc_name' => ['nullable', 'string', 'max:255', 'required_if:lines.*.is_ad_hoc,true'],
            'lines.*.ad_hoc_sku' => ['nullable', 'string', 'max:100'],
            'lines.*.description_override' => ['nullable', 'string', 'max:255'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.internal_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.actual_margin_pct' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.unit_price_after_discount' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_class_id' => ['nullable', 'ulid'],
            'lines.*.tva_rate_pct' => ['required', 'numeric', 'min:0'],
            'lines.*.total_ht' => ['required', 'numeric', 'min:0'],
            'lines.*.total_tva' => ['required', 'numeric', 'min:0'],
            'lines.*.total_ttc' => ['required', 'numeric', 'min:0'],
            'lines.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
            'lines.*.item_type_snapshot' => ['nullable', 'string', Rule::enum(ItemTypeSnapshot::class)],
        ];
    }
}
