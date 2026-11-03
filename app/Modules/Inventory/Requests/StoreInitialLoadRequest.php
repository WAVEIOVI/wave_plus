<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInitialLoadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.catalog_item_id' => ['required', 'exists:catalog_items,id'],
            'items.*.catalog_variant_id' => ['required', 'exists:catalog_variants,id'],
            'items.*.location_id' => ['required', 'exists:warehouse_locations,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.currency' => ['nullable', 'string', 'size:3'],
            'items.*.stock_lot_id' => ['nullable', 'exists:stock_lots,id'],
            'items.*.condition' => ['nullable', 'string', 'in:new,used,refurbished,damaged'],
        ];
    }
}
