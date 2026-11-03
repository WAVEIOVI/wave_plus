<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['ulid', 'sometimes', 'exists:warehouse_sites,id'],
            'code' => ['sometimes', 'string', 'max:20'],
            'name' => ['sometimes', 'array'],
            'name.en' => ['sometimes', 'string', 'max:255'],
            'name.fr' => ['nullable', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'zone_type' => ['sometimes', 'in:receiving,storage,picking,packing,shipping,staging,quarantine,returns,cold_storage'],
            'status' => ['nullable', 'in:active,inactive,maintenance'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
