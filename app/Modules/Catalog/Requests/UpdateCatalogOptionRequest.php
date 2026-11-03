<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|array',
            'name.*' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'ui_control' => 'nullable|string|in:select,radio,pills,color_picker',
            'values' => 'nullable|array',
            'values.*.id' => 'nullable|string',
            'values.*.value' => 'nullable|array',
            'values.*.sort_order' => 'nullable|integer|min:0',
        ];
    }
}
