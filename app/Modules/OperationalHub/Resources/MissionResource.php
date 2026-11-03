<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'mission_number'          => $this->mission_number,
            'swo_id'                  => $this->swo_id,
            'swo'                     => $this->whenLoaded('swo', fn () => [
                'id'               => $this->swo->id,
                'swo_number'       => $this->swo->swo_number,
                'fulfillment_mode' => $this->swo->fulfillment_mode?->value,
                'status'           => $this->swo->status?->value,
                'client'           => [
                    'id'              => $this->swo->client?->id,
                    'commercial_name' => $this->swo->client?->commercial_name,
                    'legal_name'      => $this->swo->client?->legal_name,
                    'phone'           => $this->swo->client?->phone,
                ],
            ]),
            'mode'                    => [
                'value' => $this->mode?->value ?? (string) $this->mode,
                'label' => $this->mode?->label() ?? ucfirst((string) $this->mode),
                'color' => $this->mode?->badgeColor() ?? 'primary',
            ],
            'status'                  => [
                'value' => $this->status?->value ?? (string) $this->status,
                'label' => $this->status?->label() ?? ucfirst((string) $this->status),
                'color' => $this->status?->badgeColor() ?? 'grey',
            ],
            'workshop_unit_id'        => $this->workshop_unit_id,
            'workshop_unit'           => $this->whenLoaded('workshopUnit', fn () => [
                'id'       => $this->workshopUnit->id,
                'name'     => $this->workshopUnit->getTranslations('name'),
                'code'     => $this->workshopUnit->code,
                'location' => $this->workshopUnit->location,
            ]),
            'service_site_id'         => $this->service_site_id,
            'service_site'            => $this->whenLoaded('serviceSite', fn () => [
                'id'        => $this->serviceSite->id,
                'name'      => $this->serviceSite->name,
                'address'   => $this->serviceSite->address,
                'latitude'  => $this->serviceSite->latitude,
                'longitude' => $this->serviceSite->longitude,
            ]),
            'team_id'                 => $this->team_id,
            'team'                    => $this->whenLoaded('team', fn () => [
                'id'   => $this->team->id,
                'name' => $this->team->name,
                'code' => $this->team->code,
            ]),
            'lead_technician_id'      => $this->lead_technician_id,
            'lead_technician'         => $this->whenLoaded('leadTechnician', fn () => [
                'id'    => $this->leadTechnician->id,
                'name'  => $this->leadTechnician->first_name . ' ' . $this->leadTechnician->last_name,
                'phone' => $this->leadTechnician->work_phone,
            ]),
            'vehicle_id'              => $this->vehicle_id,
            'vehicle'                 => $this->whenLoaded('vehicle', fn () => [
                'id'           => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'label'        => $this->vehicle->label,
            ]),
            'planned_date'            => $this->planned_date?->format('Y-m-d'),
            'planned_start'           => $this->planned_start?->toISOString(),
            'planned_end'             => $this->planned_end?->toISOString(),
            'planned_start_time'      => $this->planned_start_time,
            'planned_duration_hours'  => $this->planned_duration_hours,
            'actual_start_at'         => $this->actual_start_at?->toISOString(),
            'actual_end_at'           => $this->actual_end_at?->toISOString(),
            'preparation_notes'       => $this->preparation_notes,
            'client_contact_name'     => $this->client_contact_name,
            'client_reception_at'     => $this->client_reception_at?->toISOString(),
            'client_signature'        => $this->client_signature,
            'client_satisfaction'     => $this->client_satisfaction,
            'completion_notes'        => $this->completion_notes,
            'cancellation_reason'     => $this->cancellation_reason,
            'source_field_mission_id' => $this->source_field_mission_id,
            'employees'               => $this->whenLoaded('employees', fn () => $this->employees->map(fn ($emp) => [
                'id'         => $emp->id,
                'first_name' => $emp->first_name,
                'last_name'  => $emp->last_name,
                'name'       => trim($emp->first_name . ' ' . $emp->last_name),
                'email'      => $emp->work_email,
                'is_lead'    => (bool) ($emp->pivot->is_lead ?? false),
            ])),
            'attendances'             => $this->whenLoaded('attendances', fn () => $this->attendances->map(fn ($a) => [
                'id'             => $a->id,
                'employee_id'    => $a->employee_id,
                'employee_name'  => $a->employee?->first_name . ' ' . $a->employee?->last_name,
                'date'           => $a->date?->format('Y-m-d'),
                'arrival_time'   => $a->arrival_time,
                'departure_time' => $a->departure_time,
                'status'         => $a->status,
                'notes'          => $a->notes,
            ])),
            'checklists'              => $this->whenLoaded('checklists', fn () => $this->checklists->map(fn ($c) => [
                'id'    => $c->id,
                'title' => $c->title,
                'notes' => $c->notes,
                'items' => $c->items->map(fn ($i) => [
                    'id'            => $i->id,
                    'title'         => $i->title,
                    'answer'        => $i->answer,
                    'notes'         => $i->notes,
                    'is_completed'  => (bool) $i->is_completed,
                    'evidence_path' => $i->evidence_path,
                ]),
            ])),
            'interventions'           => InterventionResource::collection($this->whenLoaded('interventions')),
            'findings'                => FindingResource::collection($this->whenLoaded('findings')),
            'exit_passes'             => WorkshopExitPassResource::collection($this->whenLoaded('exitPasses')),
            'created_at'              => $this->created_at?->toISOString(),
            'updated_at'              => $this->updated_at?->toISOString(),
        ];
    }
}
