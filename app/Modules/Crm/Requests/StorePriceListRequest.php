<?php

declare(strict_types=1);

namespace App\Modules\Crm\Requests;

use App\Modules\Crm\Enums\PriceComputationMethod;
use App\Modules\Crm\Enums\PriceListType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'unique:price_lists,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(PriceListType::class)],
            'client_id' => ['nullable', 'ulid', 'exists:clients,id'],
            'segment' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'currency' => ['nullable', 'string', 'size:3'],
            'price_computation' => ['required', 'string', Rule::enum(PriceComputationMethod::class)],
            'is_active' => ['boolean'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.catalog_item_id' => ['required', 'string', 'exists:catalog_items,id'],
            'lines.*.variant_id' => ['nullable', 'string', 'exists:catalog_variants,id'],
            'lines.*.price_computation' => ['nullable', 'string', Rule::in(['fixed', 'discount_pct'])],
            'lines.*.fixed_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.min_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.max_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.computed_price' => ['required', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
            'lines.*.internal_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.sales_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
