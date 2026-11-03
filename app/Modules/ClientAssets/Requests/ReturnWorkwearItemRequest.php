<?php

namespace App\Modules\ClientAssets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnWorkwearItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition_at_return' => 'nullable|in:new,good,fair,worn,damaged',
            'return_action' => 'required|in:restock,repair,retire,lost,stolen',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
