<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceWorkOrderListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'swo_number'             => $this->swo_number,
            'sales_order_id'         => $this->sales_order_id,
            'sales_order_reference'  => $this->salesOrder?->reference,
            'fulfillment_mode'       => [
                'value' => $this->fulfillment_mode?->value ?? (string) $this->fulfillment_mode,
                'label' => $this->fulfillment_mode?->label() ?? ucfirst((string) $this->fulfillment_mode),
                'color' => $this->fulfillment_mode?->badgeColor() ?? 'primary',
            ],
            'client'                 => $this->whenLoaded('client', fn () => [
                'id'              => $this->client->id,
                'legal_name'      => $this->client->legal_name,
                'commercial_name' => $this->client->commercial_name,
            ]),
            'service_site'           => $this->whenLoaded('serviceSite', fn () => [
                'id'   => $this->serviceSite->id,
                'name' => $this->serviceSite->name,
            ]),
            'team'                   => $this->whenLoaded('team', fn () => [
                'id'   => $this->team->id,
                'name' => $this->team->name,
                'code' => $this->team->code,
            ]),
            'lead_technician'        => $this->whenLoaded('leadTechnician', fn () => [
                'id'   => $this->leadTechnician->id,
                'name' => $this->leadTechnician->first_name . ' ' . $this->leadTechnician->last_name,
            ]),
            'workshop_unit'          => $this->whenLoaded('workshopUnit', fn () => [
                'id'   => $this->workshopUnit->id,
                'name' => $this->workshopUnit->getTranslations('name'),
                'code' => $this->workshopUnit->code,
                'type' => $this->workshopUnit->type?->value,
            ]),
            'vehicle'                => $this->whenLoaded('vehicle', fn () => [
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
            'mobile_warehouse_id'    => $this->mobile_warehouse_id,
            'mobile_warehouse'       => $this->whenLoaded('mobileWarehouse', fn () => [
                'id'   => $this->mobileWarehouse->id,
                'name' => $this->mobileWarehouse->name,
                'code' => $this->mobileWarehouse->code,
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
            'scheduled_date'         => $this->scheduled_date?->format('Y-m-d'),
            'planned_date'           => $this->planned_date?->format('Y-m-d'),
            'planned_start_time'     => $this->planned_start_time,
            'planned_duration_hours' => $this->planned_duration_hours,
            'started_at'             => $this->started_at?->toISOString(),
            'completed_at'           => $this->completed_at?->toISOString(),
            'closed_at'              => $this->closed_at?->toISOString(),
            'total_items'            => $this->items_count ?? $this->items?->count() ?? 0,
            'total_missions'         => $this->missions_count ?? $this->missions?->count() ?? 0,
            'source_swo_id'          => $this->source_swo_id,
            'source_mission_id'      => $this->source_mission_id,
            'created_at'             => $this->created_at?->toISOString(),
        ];
    }
}
