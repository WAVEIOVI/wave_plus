<?php

namespace App\Modules\BusinessPartners\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier');

        return [
            'legal_name' => [
                'sometimes',
                'required',
                function ($attribute, $value, $fail) {
                    if (is_array($value)) {
                        $hasContent = collect($value)->filter(fn ($v) => is_string($v) && trim($v) !== '')->isNotEmpty();
                        if (! $hasContent) {
                            $fail(__('validation.required', ['attribute' => $attribute]));
                        }
                    } elseif (! is_string($value) || trim($value) === '') {
                        $fail(__('validation.required', ['attribute' => $attribute]));
                    }
                },
            ],
            'legal_name.*' => 'nullable|string|max:255',
            'commercial_name' => 'nullable|array',
            'commercial_name.*' => 'nullable|string|max:255',
            'tax_id' => ['nullable', 'string', 'max:50', Rule::unique('suppliers', 'tax_id')->ignore($supplierId)],
            'rne_id' => ['nullable', 'string', 'max:50', Rule::unique('suppliers', 'rne_id')->ignore($supplierId)],
            'logo' => 'required_if:logo_source,upload|nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'legal_status' => 'nullable|in:sarl,sa,suarl,snc,individual,public_entity,association,ngo,other',
            'company_size' => 'nullable|in:1-10,11-50,51-200,201-500,501-1000,1001-5000,5001-10000,10001+',
            'industry' => 'nullable|string|max:255',
            'founding_year' => 'nullable|integer|min:1800|max:'.date('Y'),
            'website' => 'nullable|url|max:255',
            'logo_path' => 'nullable|string|max:255',
            'logo_source' => 'nullable|string|in:brandfetch,logo_dev,upload,initials',
            'logo_domain' => 'nullable|string|max:255',
            'logo_external_url' => 'required_if:logo_source,brandfetch,logo_dev|nullable|url|max:2048',
            'primary_phone' => 'nullable|string|max:30',
            'primary_email' => 'nullable|email|max:255',
            'hq_address' => 'nullable|array',
            'hq_address.*' => 'nullable|string|max:255',
            'hq_city' => 'nullable|array',
            'hq_city.*' => 'nullable|string|max:255',
            'hq_postcode' => 'nullable|string|max:20',
            'hq_country' => 'nullable',
            'hq_country.*' => 'nullable|string|max:100',
            'account_manager_id' => 'nullable|ulid|exists:users,id',
            'partner_since' => 'nullable|date',
            'satisfaction_score' => 'nullable|numeric|min:0|max:10',
            'supplier_category' => 'nullable|in:manufacturer,distributor,wholesaler,general',
            'lead_time_days' => 'nullable|integer|min:0',
            'payment_terms' => 'nullable|string|max:255',
            'minimum_order_value' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:prospect,active,inactive,suspended,blacklisted,archived',
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string',
        ];
    }
}
