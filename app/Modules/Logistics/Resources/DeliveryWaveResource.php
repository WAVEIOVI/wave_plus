<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryWaveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'wave_number'   => $this->wave_number,
            'planned_date'  => $this->planned_date?->format('Y-m-d'),
            'dispatch_time' => $this->dispatch_time,
            'warehouse_id'  => $this->warehouse_id,
            'warehouse'     => $this->whenLoaded('warehouse', fn () => [
                'id'   => $this->warehouse->id,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code,
            ]),
            'vehicle_id'    => $this->vehicle_id,
            'vehicle'       => $this->whenLoaded('vehicle', fn () => [
                'id'           => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'label'        => $this->vehicle->label,
            ]),
            'approved_driver_id'     => $this->approved_driver_id,
            'approved_driver'        => $this->whenLoaded('approvedDriver', fn () => [
                'id'           => $this->approvedDriver->id,
                'driver_authorization_number' => $this->approvedDriver->driver_authorization_number,
                'employee_name' => $this->approvedDriver->employee?->full_name ?? '—',
                'driver_score'  => $this->approvedDriver->driver_score,
                'is_active'     => $this->approvedDriver->is_active,
            ]),
            'status'        => [
                'value' => $this->status?->value ?? (string) $this->status,
                'label' => $this->status?->label() ?? ucfirst((string) $this->status),
                'color' => $this->status?->badgeColor() ?? 'grey',
            ],
            'route_notes'   => $this->route_notes,
            'stops'         => $this->whenLoaded('stops', fn () => $this->stops->map(fn ($stop) => [
                'id'                     => $stop->id,
                'sequence'               => $stop->sequence,
                'dwo_id'                 => $stop->dwo_id,
                'status'                 => $stop->status?->value ?? (string) $stop->status,
                'planned_arrival'        => $stop->planned_arrival?->toISOString(),
                'actual_arrival'         => $stop->actual_arrival?->toISOString(),
                'delivery_bp_address_id' => $stop->delivery_bp_address_id,
                'address'                => $stop->deliveryAddress ? [
                    'label'  => $stop->deliveryAddress->label,
                    'street' => $stop->deliveryAddress->address_street,
                    'city'   => $stop->deliveryAddress->city,
                ] : null,
                'dwo'                    => $stop->deliveryWorkOrder ? [
                    'id'         => $stop->deliveryWorkOrder->id,
                    'dwo_number' => $stop->deliveryWorkOrder->dwo_number,
                    'client'     => $stop->deliveryWorkOrder->client?->legal_name,
                    'status'     => $stop->deliveryWorkOrder->status?->value,
                ] : null,
            ])),
            'total_dwos'    => $this->waveItems?->count() ?? 0,
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}
