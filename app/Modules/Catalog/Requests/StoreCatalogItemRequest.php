<?php

namespace App\Modules\Catalog\Requests;

use App\Modules\Catalog\Concerns\SanitizesCatalogItemPayload;
use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogItemRequest extends FormRequest
{
    use SanitizesCatalogItemPayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            // ── Identification ───────────────────────────────────────
            'code' => 'required|string|max:40|unique:catalog_items,code',
            'barcode' => 'nullable|string|max:100',
            'item_type' => 'required|in:product,consumable,eq_servicing',
            'name' => 'required|array',
            'name.*' => 'required|string|max:255',
            'short_description' => 'nullable|array',
            'short_description.*' => 'nullable|string|max:500',
            'category_id' => 'required|ulid|exists:catalog_categories,id',
            'brand_id' => 'nullable|ulid|exists:catalog_brands,id',
            'uom_id' => 'required|ulid|exists:units_of_measure,id',
            'status' => 'nullable|in:active,draft,discontinued',
            'is_stockable' => 'nullable|boolean',
            'is_purchasable' => 'nullable|boolean',
            'is_sellable' => 'nullable|boolean',

            // ── Variants ─────────────────────────────────────────────
            'variants' => 'nullable|array',
            'variants.*.sku' => 'required_with:variants|string|max:60|unique:catalog_variants,sku',
            'variants.*.barcode' => 'nullable|string|max:100|unique:catalog_variants,barcode',
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
            'product.product_type' => 'required_with:product|in:physical,digital,service',
            'product.technical_specs' => 'nullable|array',

            // ── Consumable Extension ─────────────────────────────────
            'consumable' => 'nullable|array',
            'consumable.consumable_type' => 'required_with:consumable|in:agents_and_disposables,replacement_parts,safety_signage,accessories',
            'consumable.technical_specs' => 'nullable|array',

            // ── Equipment Servicing Extension ────────────────────────
            'eq_servicing' => 'nullable|array',
            'eq_servicing.eq_servicing_type' => 'required_with:eq_servicing|in:inspection_and_testing,maintenance_and_repair,refill_recharge,calibration_and_certification,installation_decommissioning',
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
