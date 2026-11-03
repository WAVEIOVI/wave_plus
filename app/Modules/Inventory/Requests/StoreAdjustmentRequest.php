<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdjustmentRequest extends FormRequest
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
            'location_id' => ['ulid', 'required', 'exists:warehouse_locations,id'],
            'stock_lot_id' => ['ulid', 'nullable', 'exists:stock_lots,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'adjustment_type' => ['required', 'in:plus,minus'],
            'reason' => ['required', 'string', 'max:2000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['nullable', 'in:new,used,refurbished,damaged'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required for all stock adjustments.',
            'quantity.gt' => 'Adjustment quantity must be greater than zero.',
        ];
    }
}
