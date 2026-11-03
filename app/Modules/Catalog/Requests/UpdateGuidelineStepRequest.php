<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuidelineStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sort_order' => 'sometimes|integer|min:1',
            'instruction' => 'sometimes|array',
            'instruction.*' => 'required|string|max:500',
            'step_type' => 'sometimes|in:boolean,numeric,text',
            'is_required' => 'nullable|boolean',
            'expected_value' => 'nullable|string|max:100',
        ];
    }
}
