<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceWorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'swo_number'             => $this->swo_number,
            'sales_order_id'         => $this->sales_order_id,
            'sales_order'            => $this->whenLoaded('salesOrder', fn () => [
                'id'                      => $this->salesOrder->id,
                'reference'               => $this->salesOrder->reference,
                'status'                  => $this->salesOrder->status?->value ?? (string) $this->salesOrder->status,
                'fulfillment_status'      => method_exists($this->salesOrder, 'calculateFulfillmentStatus') ? $this->salesOrder->calculateFulfillmentStatus() : null,
                'requested_delivery_date' => $this->salesOrder->requested_delivery_date_from?->format('Y-m-d'),
            ]),
            'fulfillment_mode'       => [
                'value' => $this->fulfillment_mode?->value ?? (string) $this->fulfillment_mode,
                'label' => $this->fulfillment_mode?->label() ?? ucfirst((string) $this->fulfillment_mode),
                'color' => $this->fulfillment_mode?->badgeColor() ?? 'primary',
            ],
            'client_id'              => $this->client_id,
            'client'                 => $this->whenLoaded('client', fn () => [
                'id'              => $this->client->id,
                'legal_name'      => $this->client->legal_name,
                'commercial_name' => $this->client->commercial_name,
                'phone'           => $this->client->phone,
                'email'           => $this->client->email,
            ]),
            'service_site_id'        => $this->service_site_id,
            'service_site'           => $this->whenLoaded('serviceSite', fn () => [
                'id'       => $this->serviceSite->id,
                'name'     => $this->serviceSite->name,
                'address'  => $this->serviceSite->address,
                'latitude' => $this->serviceSite->latitude,
                'longitude'=> $this->serviceSite->longitude,
            ]),
            'team_id'                => $this->team_id,
            'team'                   => $this->whenLoaded('team', fn () => [
                'id'   => $this->team->id,
                'name' => $this->team->name,
                'code' => $this->team->code,
            ]),
            'lead_technician_id'     => $this->lead_technician_id,
            'lead_technician'        => $this->whenLoaded('leadTechnician', fn () => [
                'id'    => $this->leadTechnician->id,
                'name'  => $this->leadTechnician->first_name . ' ' . $this->leadTechnician->last_name,
                'email' => $this->leadTechnician->work_email,
                'phone' => $this->leadTechnician->work_phone,
            ]),
            'workshop_unit_id'       => $this->workshop_unit_id,
            'workshop_unit'          => $this->whenLoaded('workshopUnit', fn () => [
                'id'       => $this->workshopUnit->id,
                'name'     => $this->workshopUnit->getTranslations('name'),
                'code'     => $this->workshopUnit->code,
                'type'     => $this->workshopUnit->type?->value,
                'location' => $this->workshopUnit->location,
            ]),
            'vehicle_id'             => $this->vehicle_id,
            'vehicle'                => $this->whenLoaded('vehicle', fn () => [
                'id'           => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'label'        => $this->vehicle->label,
            ]),
            'approved_driver_id'     => $this->approved_driver_id,
            'approved_driver'        => $this->whenLoaded('approvedDriver', fn () => [
                'id'           => $this->approvedDriver->id,
                'driver_authorization_number' => $this->approvedDriver->driver_authorization_number,
                'employee_name' => $this->approvedDriver->employee?->full_name ?? $this->approvedDriver->employee?->first_name ?? '—',
                'license_class' => $this->approvedDriver->license_category,
                'driver_score'  => $this->approvedDriver->driver_score,
                'is_active'     => $this->approvedDriver->is_active,
            ]),
            'mobile_warehouse_id'    => $this->mobile_warehouse_id,
            'mobile_warehouse'       => $this->whenLoaded('mobileWarehouse', fn () => [
                'id'   => $this->mobileWarehouse->id,
                'name' => $this->mobileWarehouse->name,
                'code' => $this->mobileWarehouse->code,
                'vehicle_id' => $this->mobileWarehouse->vehicle_id,
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
            'planned_start'          => $this->planned_start?->toISOString(),
            'planned_end'            => $this->planned_end?->toISOString(),
            'planned_start_time'     => $this->planned_start_time,
            'planned_duration_hours' => $this->planned_duration_hours,
            'checklist_template_id'  => $this->checklist_template_id,
            'checklist_template'     => $this->whenLoaded('checklistTemplate', fn () => [
                'id'   => $this->checklistTemplate->id,
                'code' => $this->checklistTemplate->code,
                'name' => $this->checklistTemplate->getTranslations('name'),
            ]),
            'started_at'             => $this->started_at?->toISOString(),
            'completed_at'           => $this->completed_at?->toISOString(),
            'closed_at'              => $this->closed_at?->toISOString(),
            'notes'                  => $this->notes,
            'cancellation_reason'    => $this->cancellation_reason,
            'source_swo_id'          => $this->source_swo_id,
            'source_mission_id'      => $this->source_mission_id,
            'locations'              => $this->whenLoaded('locations', fn () => $this->locations->map(fn ($loc) => [
                'id'             => $loc->id,
                'client_site_id' => $loc->client_site_id,
                'sort_order'     => $loc->sort_order,
                'client_site'    => $loc->clientSite ? [
                    'id'      => $loc->clientSite->id,
                    'name'    => $loc->clientSite->name,
                    'address' => $loc->clientSite->address,
                ] : null,
            ])),
            'employees'              => $this->whenLoaded('employees', fn () => $this->employees->map(fn ($emp) => [
                'id'         => $emp->id,
                'first_name' => $emp->first_name,
                'last_name'  => $emp->last_name,
                'name'       => trim($emp->first_name . ' ' . $emp->last_name),
                'email'      => $emp->work_email,
                'is_lead'    => (bool) ($emp->pivot->is_lead ?? false),
            ])),
            'items'                  => SwoItemResource::collection($this->whenLoaded('items')),
            'missions'               => MissionListResource::collection($this->whenLoaded('missions')),
            'status_histories'       => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($h) => [
                'id'          => $h->id,
                'from_status' => $h->from_status,
                'to_status'   => $h->to_status,
                'changed_by'  => $h->changer?->name,
                'changed_at'  => $h->changed_at?->toISOString(),
                'notes'       => $h->notes,
            ])),
            'created_at'             => $this->created_at?->toISOString(),
            'updated_at'             => $this->updated_at?->toISOString(),
        ];
    }
}
