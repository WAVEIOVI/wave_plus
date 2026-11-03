<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Requests;

use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\ClientAssets\Models\ClientSite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateClientSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->route('site') ?: $this->route('id');

        return [
            'client_id' => 'sometimes|required|ulid|exists:clients,id',
            'bp_address_id' => 'nullable|ulid|exists:bp_addresses,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'site_type' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive,under_construction,decommissioned',
            'risk_class' => 'nullable|string|max:50',
            'operating_hours' => 'nullable|string|max:255',
            'access_instructions' => 'nullable|string',
            'safety_instructions' => 'nullable|string',
            'special_instructions' => 'nullable|string',
            'opened_at' => 'nullable|date',
            'closed_at' => 'nullable|date|after_or_equal:opened_at',
            'notes' => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $siteId = $this->route('site') ?: $this->route('id');
            $existing = is_string($siteId) ? ClientSite::find($siteId) : null;

            if ($existing && $this->filled('client_id') && $this->input('client_id') !== $existing->client_id) {
                if ($existing->equipment()->exists()) {
                    $validator->errors()->add(
                        'client_id',
                        'Cannot change the site client while equipment is assigned to this site.'
                    );

                    return;
                }
            }

            $clientId = $this->input('client_id', $existing?->client_id);
            $addressId = $this->input('bp_address_id', $existing?->bp_address_id);

            if ($clientId && $addressId) {
                $address = BpAddress::find($addressId);
                if ($address) {
                    $isClientAddress = ($address->addressable_type === Client::class || $address->addressable_type === 'client')
                        && $address->addressable_id === $clientId;

                    if (! $isClientAddress) {
                        $validator->errors()->add('bp_address_id', 'The selected address does not belong to the selected client.');
                    }
                }
            }
        });
    }
}
