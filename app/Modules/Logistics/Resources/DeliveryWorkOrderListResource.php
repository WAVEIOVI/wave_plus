<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryWorkOrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'dwo_number'             => $this->dwo_number,
            'sales_order_id'         => $this->sales_order_id,
            'sales_order_reference'  => $this->salesOrder?->reference,
            'client'                 => $this->whenLoaded('client', fn () => [
                'id'              => $this->client->id,
                'legal_name'      => $this->client->legal_name,
                'commercial_name' => $this->client->commercial_name,
            ]),
            'warehouse'              => $this->whenLoaded('warehouse', fn () => [
                'id'   => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ]),
            'delivery_address'       => $this->delivery_address_snapshot ?? ($this->deliveryAddress ? [
                'label'   => $this->deliveryAddress->label,
                'street'  => $this->deliveryAddress->address_street,
                'city'    => $this->deliveryAddress->city,
                'country' => $this->deliveryAddress->country,
            ] : null),
            'driver'                 => $this->whenLoaded('driver', fn () => [
                'id'   => $this->driver->id,
                'name' => $this->driver->name,
            ]),
            'vehicle'                => $this->whenLoaded('vehicle', fn () => [
                'id'           => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'label'        => $this->vehicle->label,
            ]),
            'wave'                   => $this->whenLoaded('wave', fn () => [
                'id'          => $this->wave->id,
                'wave_number' => $this->wave->wave_number,
            ]),
            'status'                 => [
                'value' => $this->status?->value ?? (string) $this->status,
                'label' => $this->status?->label() ?? ucfirst((string) $this->status),
                'color' => $this->status?->badgeColor() ?? 'grey',
            ],
            'priority'               => [
                'value' => $this->priority?->value ?? (string) $this->priority,
                'label' => $this->priority?->label() ?? ucfirst((string) $this->priority),
                'color' => $this->priority?->badgeColor() ?? 'grey',
            ],
            'fulfillment_method'     => $this->fulfillment_method,
            'delivery_service_provider_id' => $this->delivery_service_provider_id,
            'delivery_service_provider' => $this->whenLoaded('deliveryServiceProvider', fn () => [
                'id'          => $this->deliveryServiceProvider->id,
                'provider_code' => $this->deliveryServiceProvider->provider_code,
                'name'        => $this->deliveryServiceProvider->serviceProvider?->name ?? $this->deliveryServiceProvider->provider_code,
            ]),
            'approved_driver_id'     => $this->approved_driver_id,
            'approved_driver'        => $this->whenLoaded('approvedDriver', fn () => [
                'id'           => $this->approvedDriver->id,
                'driver_authorization_number' => $this->approvedDriver->driver_authorization_number,
                'employee_name' => $this->approvedDriver->employee?->full_name ?? '—',
                'driver_score'  => $this->approvedDriver->driver_score,
                'is_active'     => $this->approvedDriver->is_active,
            ]),
            'mobile_warehouse_id'    => $this->mobile_warehouse_id,
            'mobile_warehouse'       => $this->whenLoaded('mobileWarehouse', fn () => [
                'id'   => $this->mobileWarehouse->id,
                'name' => $this->mobileWarehouse->name,
                'code' => $this->mobileWarehouse->code,
            ]),
            'scheduled_date'         => $this->scheduled_date?->format('Y-m-d'),
            'planned_date'           => $this->planned_date?->format('Y-m-d'),
            'dispatched_at'          => $this->dispatched_at?->toISOString(),
            'delivered_at'           => $this->delivered_at?->toISOString(),
            'eta'                    => $this->eta?->toISOString(),
            'total_lines'            => $this->lines_count ?? $this->lines?->count() ?? 0,
            'total_qty_allocated'    => (float) ($this->lines?->sum('qty_allocated') ?? 0),
            'total_qty_picked'       => (float) ($this->lines?->sum('qty_picked') ?? 0),
            'total_qty_delivered'    => (float) ($this->lines?->sum('qty_delivered') ?? 0),
            'created_at'             => $this->created_at?->toISOString(),
        ];
    }
}
