<?php

namespace App\Modules\BusinessPartners\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBpAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'nullable|string|in:headquarters,office,branch,billing,shipping,factory,workshop,industrial,construction_site,warehouse,depot,retail,hotel,restaurant,government,hospital,school,datacenter,residential,operational_site,other',
            'is_primary' => 'nullable|boolean',
            'is_billing_default' => 'nullable|boolean',
            'is_shipping_default' => 'nullable|boolean',
            'label' => 'nullable',
            'label.*' => 'nullable|string|max:255',
            'address_street' => 'sometimes|required',
            'address_street.*' => 'nullable|string|max:255',
            'address_street2' => 'nullable',
            'address_street2.*' => 'nullable|string|max:255',
            'city' => 'sometimes|required',
            'city.*' => 'nullable|string|max:255',
            'state' => 'nullable',
            'state.*' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable',
            'country.*' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'responsible' => 'nullable|string|max:255',
            'nearest_fire_station' => 'nullable|string|max:255',
            'work_team' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string',
        ];
    }
}
