<?php

namespace App\Modules\BusinessPartners\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBpVatCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'certificate_number' => 'required|string|max:255',
            'issuing_authority' => 'nullable|string|max:255',
            'issue_date' => 'required|date',
            'expiration_date' => 'nullable|date|after_or_equal:issue_date',
            'status' => 'nullable|in:active,expired,revoked,pending_renewal',
            'document_path' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
