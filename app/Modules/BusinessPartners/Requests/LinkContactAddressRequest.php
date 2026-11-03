<?php

namespace App\Modules\BusinessPartners\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkContactAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bp_contact_id' => 'nullable|ulid|exists:bp_contacts,id',
            'bp_address_id' => 'required|ulid|exists:bp_addresses,id',
            'is_primary_location' => 'nullable|boolean',
        ];
    }
}
