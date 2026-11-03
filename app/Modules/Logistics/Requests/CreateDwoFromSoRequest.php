<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDwoFromSoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Controller enforces authorize('create', DeliveryWorkOrder::class)
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_order_id' => 'required|ulid|exists:sales_orders,id',
            'warehouse_id' => 'nullable|ulid|exists:warehouse_sites,id',
            'delivery_bp_address_id' => 'nullable|ulid|exists:bp_addresses,id',
            'delivery_site_id' => 'nullable|ulid|exists:client_sites,id',
            'scheduled_date' => 'nullable|date',
            'planned_date' => 'nullable|date',
            'priority' => 'nullable|in:normal,high,urgent',
            'fulfillment_method' => 'nullable|string|in:own_fleet,third_party,carrier,customer_pickup',
            'delivery_service_provider_id' => 'nullable|ulid|exists:delivery_service_providers,id',
            'carrier_name' => 'nullable|string|max:120',
            'estimated_delivery_cost' => 'nullable|numeric|min:0',
            'actual_delivery_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'driver_id' => 'nullable|ulid|exists:users,id',
            'approved_driver_id' => 'nullable|ulid|exists:hr_approved_drivers,id',
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
            'mobile_warehouse_id' => 'nullable|ulid|exists:warehouse_sites,id',
            'lines' => 'required|array|min:1',
            'lines.*.so_line_id' => 'required|string|exists:sales_order_lines,id',
            'lines.*.qty_to_deliver' => 'required|numeric|gt:0',
            'lines.*.warehouse_location_id' => 'nullable|string|exists:warehouse_locations,id',
            'lines.*.proposed_catalog_item_id' => 'nullable|string|exists:catalog_items,id',
            'lines.*.proposed_catalog_variant_id' => 'nullable|string|exists:catalog_variants,id',
            'lines.*.substitution_reason' => 'nullable|string|max:2000',
        ];
    }
}
