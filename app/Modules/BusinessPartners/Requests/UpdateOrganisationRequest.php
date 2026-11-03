<?php

namespace App\Modules\BusinessPartners\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orgId = $this->route('organisation');

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
            'commercial_name' => 'nullable',
            'commercial_name.en' => 'nullable|string|max:255',
            'commercial_name.fr' => 'nullable|string|max:255',
            'commercial_name.ar' => 'nullable|string|max:255',
            'tax_id' => ['nullable', 'string', 'max:50', Rule::unique('organisations', 'tax_id')->ignore($orgId)],
            'rne_id' => ['nullable', 'string', 'max:50', Rule::unique('organisations', 'rne_id')->ignore($orgId)],
            'primary_phone' => 'nullable|string|max:30',
            'primary_email' => 'nullable|email|max:255',
            'hq_address' => 'nullable',
            'hq_address.en' => 'nullable|string|max:255',
            'hq_address.fr' => 'nullable|string|max:255',
            'hq_address.ar' => 'nullable|string|max:255',
            'hq_city' => 'nullable',
            'hq_city.en' => 'nullable|string|max:255',
            'hq_city.fr' => 'nullable|string|max:255',
            'hq_city.ar' => 'nullable|string|max:255',
            'hq_postcode' => 'nullable|string|max:20',
            'hq_country' => 'nullable',
            'hq_country.*' => 'nullable|string|max:100',
            'account_manager_id' => 'nullable|ulid|exists:users,id',
            'partner_since' => 'nullable|date',
            'organisation_type' => 'sometimes|required|in:government_agency,municipality,public_institution,ngo,association,professional_body,trade_union,international_org,other',
            'registration_number' => 'nullable|string|max:255',
            'registration_date' => 'nullable|date',
            'accreditation_body' => 'nullable|string|max:255',
            'regulatory_authority' => 'nullable|string|max:255',
            'parent_organisation' => 'nullable|string|max:255',
            'member_count' => 'nullable|integer|min:0',
            'scope' => 'sometimes|required|in:local,regional,national,international',
            'partnership_start_date' => 'nullable|date',
            'partnership_end_date' => 'nullable|date|after_or_equal:partnership_start_date',
            'status' => 'nullable|in:prospect,active,inactive,archived',
            'logo' => 'required_if:logo_source,upload|nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'logo_source' => 'nullable|string|in:brandfetch,logo_dev,upload,initials',
            'logo_domain' => 'nullable|string|max:255',
            'logo_external_url' => 'required_if:logo_source,brandfetch,logo_dev|nullable|url|max:2048',
            'notes' => 'nullable',
            'notes.en' => 'nullable|string',
            'notes.fr' => 'nullable|string',
            'notes.ar' => 'nullable|string',
        ];
    }
}
