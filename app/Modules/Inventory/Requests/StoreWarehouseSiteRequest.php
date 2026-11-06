<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:warehouse_sites,code'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.fr' => ['nullable', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'manager_id' => ['ulid', 'nullable', 'exists:users,id'],
            'status' => ['nullable', 'in:active,inactive,maintenance'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'site_type' => ['nullable', 'in:fixed,mobile'],
            'vehicle_id' => ['nullable', 'ulid', 'exists:vehicles,id'],
            'mobile_status' => ['nullable', 'string', 'max:50'],
            'current_location' => ['nullable', 'string', 'max:255'],
            'location_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
