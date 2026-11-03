<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuidelineStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guideline_id' => 'required|ulid|exists:guidelines,id',
            'sort_order' => 'required|integer|min:1',
            'instruction' => 'required|array',
            'instruction.*' => 'required|string|max:500',
            'step_type' => 'required|in:boolean,numeric,text',
            'is_required' => 'nullable|boolean',
            'expected_value' => 'nullable|string|max:100',
        ];
    }
}
