<?php

namespace App\Modules\ClientAssets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignWorkwearItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|ulid|exists:workwear_employees,id',
            'item_ids' => 'required|array|min:1|max:50',
            'item_ids.*' => 'ulid|exists:workwear_items,id',
            'condition_at_assign' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
