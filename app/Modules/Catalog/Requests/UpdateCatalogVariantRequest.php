<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variantId = $this->route('variant');

        $variant = \App\Modules\Catalog\Models\CatalogVariant::find($variantId);
        $catalogItemId = $variant ? $variant->catalog_item_id : null;

        return [
            'sku' => "nullable|string|max:60|unique:catalog_variants,sku,{$variantId},id,catalog_item_id,{$catalogItemId}",
            'price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
            'option_value_ids' => 'nullable|array',
            'option_value_ids.*' => 'nullable|ulid|exists:catalog_option_values,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        // If stock is explicitly null, remove it from the request so it won't be updated
        if ($this->has('stock') && $this->input('stock') === null) {
            $this->request->remove('stock');
        }
    }
}
