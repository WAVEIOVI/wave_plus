<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Requests;

use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreClientSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|ulid|exists:clients,id',
            'bp_address_id' => 'nullable|ulid|exists:bp_addresses,id',
            'site_code' => 'nullable|string|max:30|unique:client_sites,site_code',
            'name' => 'required|string|max:255',
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
            $clientId = $this->input('client_id');
            $addressId = $this->input('bp_address_id');

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
