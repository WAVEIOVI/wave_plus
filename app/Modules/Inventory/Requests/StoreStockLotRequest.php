<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'catalog_item_id' => ['ulid', 'required', 'exists:catalog_items,id'],
            'supplier_id' => ['ulid', 'nullable', 'exists:suppliers,id'],
            'lot_number' => ['required', 'string', 'max:100'],
            'manufacture_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:manufacture_date'],
            'status' => ['nullable', 'in:active,expired,quarantined,recalled'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
