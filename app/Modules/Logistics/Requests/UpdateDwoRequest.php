<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDwoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => 'nullable|ulid|exists:warehouse_sites,id',
            'delivery_bp_address_id' => 'nullable|ulid|exists:bp_addresses,id',
            'delivery_site_id' => 'nullable|ulid|exists:client_sites,id',
            'driver_id' => 'nullable|ulid|exists:users,id',
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
            'scheduled_date' => 'nullable|date',
            'planned_date' => 'nullable|date',
            'priority' => 'nullable|in:normal,high,urgent',
            'fulfillment_method' => 'nullable|string|in:own_fleet,third_party,carrier,customer_pickup',
            'delivery_service_provider_id' => 'nullable|ulid',
            'carrier_name' => 'nullable|string|max:120',
            'estimated_delivery_cost' => 'nullable|numeric|min:0',
            'actual_delivery_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
