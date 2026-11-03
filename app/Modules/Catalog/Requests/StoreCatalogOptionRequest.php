<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catalog_item_id' => 'required|ulid|exists:catalog_items,id',
            'name' => 'required|array',
            'name.*' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'ui_control' => 'nullable|string|in:select,radio,pills,color_picker',

            // Nested option values (optional bulk create)
            'values' => 'nullable|array',
            'values.*.value' => 'required_with:values|array',
            'values.*.value.*' => 'required|string|max:255',
            'values.*.sort_order' => 'nullable|integer|min:0',
        ];
    }
}
