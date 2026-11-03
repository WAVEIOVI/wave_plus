<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catalog_item_id' => 'required|ulid|exists:catalog_items,id',
            'sku' => 'required|string|max:60|unique:catalog_variants,sku,NULL,id,catalog_item_id,'.$this->input('catalog_item_id'),
            'price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'required|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
            'option_value_ids' => 'nullable|array',
            'option_value_ids.*' => 'nullable|ulid|exists:catalog_option_values,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure stock is always a number, never null
        $this->merge([
            'stock' => $this->input('stock') ?? 0,
        ]);
    }
}
