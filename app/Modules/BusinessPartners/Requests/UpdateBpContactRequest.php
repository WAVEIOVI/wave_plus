<?php

namespace App\Modules\BusinessPartners\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBpContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|required',
            'first_name.*' => 'nullable|string|max:255',
            'last_name' => 'sometimes|required',
            'last_name.*' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female',
            'avatar_path' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'mobile' => 'nullable|string|max:30',
            'whatsapp_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'is_primary' => 'nullable|boolean',
            'is_decision_maker' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string',
        ];
    }
}
