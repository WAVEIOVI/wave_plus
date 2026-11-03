<?php

namespace App\Modules\BusinessPartners\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBpBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_name' => 'required|string|max:255',
            'bank_code' => 'nullable|string|max:10',
            'branch' => 'nullable|string|max:255',
            'account_holder' => 'nullable|string|max:255',
            'rib' => 'nullable|string|max:23',
            'iban' => 'nullable|string|max:34',
            'swift_bic' => 'nullable|string|max:11',
            'account_number' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:10',
            'is_primary' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
            'usage' => 'nullable|in:payments,receipts,both',
            'notes' => 'nullable|string',
        ];
    }
}
