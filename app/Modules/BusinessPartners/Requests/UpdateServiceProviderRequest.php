<?php

namespace App\Modules\BusinessPartners\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $spId = $this->route('service_provider');

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
            'tax_id' => ['nullable', 'string', 'max:50', Rule::unique('service_providers', 'tax_id')->ignore($spId)],
            'rne_id' => ['nullable', 'string', 'max:50', Rule::unique('service_providers', 'rne_id')->ignore($spId)],
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
            'hq_city' => 'nullable|array',
            'hq_postcode' => 'nullable|string|max:20',
            'hq_country' => 'nullable',
            'hq_country.*' => 'nullable|string|max:100',
            'account_manager_id' => 'nullable|ulid|exists:users,id',
            'partner_since' => 'nullable|date',
            'satisfaction_score' => 'nullable|numeric|min:0|max:10',
            'provider_tier' => 'nullable|in:standard,preferred,certified_partner',
            'availability_status' => 'nullable|in:available,partially_available,fully_booked,inactive',
            'contract_type' => 'nullable|in:fixed_price,time_and_materials,retainer,per_mission',
            'base_rate' => 'nullable|numeric|min:0',
            'rate_currency' => 'nullable|string|max:10',
            'specialties' => 'nullable|array',
            'service_zones' => 'nullable|array',
            'certifications' => 'nullable|array',
            'status' => 'nullable|in:prospect,active,inactive,suspended,blacklisted,archived',
            'notes' => 'nullable|array',
        ];
    }
}
