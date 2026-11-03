<?php

namespace App\Modules\Catalog\Requests;

use App\Modules\Catalog\Concerns\SanitizesCatalogItemPayload;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogItemRequest extends FormRequest
{
    use SanitizesCatalogItemPayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $itemId = $this->route('catalog_item') instanceof \Illuminate\Database\Eloquent\Model
            ? $this->route('catalog_item')->getKey()
            : ($this->route('catalog_item') ?? $this->route('catalogItem'));

        $rules = [
            // ── Identification ───────────────────────────────────────
            'code' => [
                'nullable',
                'string',
                'max:40',
                \Illuminate\Validation\Rule::unique('catalog_items', 'code')->ignore($itemId),
            ],
            'barcode' => 'nullable|string|max:100',
            'item_type' => 'nullable|in:product,consumable,eq_servicing',
            'name' => 'nullable|array',
            'name.*' => 'nullable|string|max:255',
            'short_description' => 'nullable|array',
            'short_description.*' => 'nullable|string|max:500',
            'category_id' => 'nullable|ulid|exists:catalog_categories,id',
            'brand_id' => 'nullable|ulid|exists:catalog_brands,id',
            'uom_id' => 'nullable|ulid|exists:units_of_measure,id',
            'status' => 'nullable|in:active,draft,discontinued',
            'is_stockable' => 'nullable|boolean',
            'is_purchasable' => 'nullable|boolean',
            'is_sellable' => 'nullable|boolean',

            // ── Variants ─────────────────────────────────────────────
            'variants' => 'required|array|min:1',
            'variants.*.id' => 'nullable|string|exists:catalog_variants,id',
            'variants.*.sku' => 'required_with:variants|string|max:60',
            'variants.*.barcode' => 'nullable|string|max:100',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.cost_price' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'nullable|numeric|min:0',
            'variants.*.name' => 'nullable|string|max:255',
            'variants.*.markup' => 'nullable|numeric|min:0',
            'variants.*.tax_class' => 'nullable|in:standard_19pct,reduced_7pct,exempt',
            'variants.*.min_stock_level' => 'nullable|numeric|min:0',
            'variants.*.reorder_point' => 'nullable|numeric|min:0',
            'variants.*.reorder_qty' => 'nullable|numeric|min:0',
            'variants.*.lead_time_days' => 'nullable|integer|min:0',

            // ── Tags & Certifications ────────────────────────────────
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|string|max:100',
            'certifications' => 'nullable|array',
            'certifications.*' => 'nullable|string|max:100',

            // ── Suppliers (pivot) ────────────────────────────────────
            'suppliers' => 'nullable|array',
            'suppliers.*.supplier_id' => 'required_with:suppliers|exists:suppliers,id',
            'suppliers.*.supplier_reference' => 'nullable|string|max:100',
            'suppliers.*.supplier_price' => 'nullable|numeric|min:0',
            'suppliers.*.currency' => 'nullable|string|size:3',
            'suppliers.*.lead_time_days' => 'nullable|integer|min:0',
            'suppliers.*.min_order_qty' => 'nullable|numeric|min:0',
            'suppliers.*.is_preferred' => 'nullable|boolean',

            'preferred_supplier_id' => 'nullable|ulid|exists:suppliers,id',
            'warranty_months' => 'nullable|integer|min:0',
            'internal_notes' => 'nullable|string',

            // ── Product Extension ────────────────────────────────────
            'product' => 'nullable|array',
            'product.product_type' => 'nullable|in:physical,digital,service',
            'product.technical_specs' => 'nullable|array',

            // ── Consumable Extension ─────────────────────────────────
            'consumable' => 'nullable|array',
            'consumable.consumable_type' => 'nullable|in:agents_and_disposables,replacement_parts,safety_signage,accessories',
            'consumable.technical_specs' => 'nullable|array',

            // ── Equipment Servicing Extension ────────────────────────
            'eq_servicing' => 'nullable|array',
            'eq_servicing.eq_servicing_type' => 'nullable|in:inspection_and_testing,maintenance_and_repair,refill_recharge,calibration_and_certification,installation_decommissioning',
            'eq_servicing.delivery_mode' => 'nullable|string|max:50',
            'eq_servicing.billable_unit' => 'nullable|string|max:50',
            'eq_servicing.duration_minutes' => 'nullable|integer|min:0',
            'eq_servicing.is_recurring' => 'nullable|boolean',
            'eq_servicing.required_certifications' => 'nullable|array',
            'eq_servicing.technical_specs' => 'nullable|array',
            'eq_servicing.consumables' => 'nullable|array',
            'eq_servicing.consumables.*.catalog_item_id' => 'required|exists:catalog_items,id',
            'eq_servicing.consumables.*.quantity' => 'required|numeric|min:0',
            'eq_servicing.consumables.*.unit' => 'nullable|string|max:30',
        ];

        return $rules;
    }
}
