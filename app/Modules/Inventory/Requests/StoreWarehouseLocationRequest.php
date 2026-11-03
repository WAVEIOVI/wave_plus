<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zone_id' => ['ulid', 'required', 'exists:warehouse_zones,id'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:warehouse_locations,barcode'],
            'aisle' => ['nullable', 'string', 'max:10'],
            'rack' => ['nullable', 'string', 'max:10'],
            'shelf' => ['nullable', 'string', 'max:10'],
            'bin' => ['nullable', 'string', 'max:10'],
            'location_type' => ['required', 'in:shelf,bin,floor,pallet,rack,bulk,pick,overstock,staging,quarantine,returns,cross_dock,cold_storage,hazmat'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'max_volume_m3' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
