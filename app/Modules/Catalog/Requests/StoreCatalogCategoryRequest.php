<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|array',
            'name.*' => 'required|string|max:255',
            'parent_id' => 'nullable|ulid|exists:catalog_categories,id',
            'item_type_scope' => 'nullable|array',
            'item_type_scope.*' => 'nullable|in:product,consumable,eq_servicing',
            'item_subtype_scope' => 'nullable|array',
            'item_subtype_scope.*' => 'nullable|in:physical,digital,service,agents_and_disposables,replacement_parts,safety_signage,accessories,inspection_and_testing,maintenance_and_repair,refill_recharge,calibration_and_certification,installation_decommissioning',
            'icon' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}
