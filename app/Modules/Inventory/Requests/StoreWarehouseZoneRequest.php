<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['ulid', 'required', 'exists:warehouse_sites,id'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.fr' => ['nullable', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'zone_type' => ['required', 'in:receiving,storage,picking,packing,shipping,staging,quarantine,returns,cold_storage'],
            'status' => ['nullable', 'in:active,inactive,maintenance'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
