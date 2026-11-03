<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catalog_item_id' => ['ulid', 'required', 'exists:catalog_items,id'],
            'catalog_variant_id' => ['ulid', 'required', 'exists:catalog_variants,id'],
            'from_location_id' => ['ulid', 'required', 'exists:warehouse_locations,id', 'different:to_location_id'],
            'to_location_id' => ['ulid', 'required', 'exists:warehouse_locations,id', 'different:from_location_id'],
            'stock_lot_id' => ['ulid', 'nullable', 'exists:stock_lots,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'condition' => ['nullable', 'in:new,used,refurbished,damaged'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_location_id.different' => 'Source and destination locations must be different.',
            'to_location_id.different' => 'Source and destination locations must be different.',
        ];
    }
}
