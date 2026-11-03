<?php

declare(strict_types=1);

namespace App\Modules\Crm\Requests;

use App\Modules\Crm\Enums\PriceComputationMethod;
use App\Modules\Crm\Enums\PriceListType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:40', Rule::unique('price_lists', 'code')->ignore($this->route('priceList'))],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::enum(PriceListType::class)],
            'client_id' => ['nullable', 'ulid', 'exists:clients,id'],
            'segment' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['sometimes', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'currency' => ['nullable', 'string', 'size:3'],
            'price_computation' => ['sometimes', 'string', Rule::enum(PriceComputationMethod::class)],
            'is_active' => ['boolean'],

            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'string'],
            'lines.*.catalog_item_id' => ['required_with:lines', 'string', 'exists:catalog_items,id'],
            'lines.*.variant_id' => ['nullable', 'string', 'exists:catalog_variants,id'],
            'lines.*.price_computation' => ['nullable', 'string', Rule::in(['fixed', 'discount_pct'])],
            'lines.*.fixed_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.min_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.max_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.computed_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
            'lines.*.internal_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.sales_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
