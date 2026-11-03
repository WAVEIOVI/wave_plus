<?php

declare(strict_types=1);

namespace App\Modules\Crm\Requests;

use App\Modules\Crm\Enums\RfqSource;
use App\Modules\Crm\Enums\RfqStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['sometimes', 'ulid', 'exists:clients,id'],
            'source' => ['sometimes', 'string', Rule::enum(RfqSource::class)],
            'received_at' => ['sometimes', 'date'],
            'response_deadline' => ['nullable', 'date'],
            'client_reference' => ['nullable', 'string', 'max:100'],
            'site_id' => ['nullable', 'ulid'],
            'assigned_to' => ['nullable', 'ulid', 'exists:users,id'],
            'status' => ['sometimes', 'string', Rule::enum(RfqStatus::class)],
            'client_note' => ['nullable', 'string'],
            'internal_note' => ['nullable', 'string'],

            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'string'],
            'lines.*.is_ad_hoc' => ['nullable', 'boolean'],
            'lines.*.catalog_item_id' => ['nullable', 'string', 'exists:catalog_items,id', 'required_unless:lines.*.is_ad_hoc,true'],
            'lines.*.variant_id' => ['nullable', 'string'],
            'lines.*.ad_hoc_name' => ['nullable', 'string', 'max:255', 'required_if:lines.*.is_ad_hoc,true'],
            'lines.*.ad_hoc_sku' => ['nullable', 'string', 'max:100'],
            'lines.*.qty_requested' => ['required_with:lines', 'numeric', 'min:0.001'],
            'lines.*.internal_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.target_margin_pct' => ['nullable', 'numeric', 'min:0'],
            'lines.*.suggested_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.proposed_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.actual_margin_pct' => ['nullable', 'numeric'],
            'lines.*.tva_rate_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
