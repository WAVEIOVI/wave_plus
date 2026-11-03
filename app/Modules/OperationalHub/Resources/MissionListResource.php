<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MissionListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'mission_number'         => $this->mission_number,
            'swo_id'                 => $this->swo_id,
            'swo_number'             => $this->swo?->swo_number,
            'mode'                   => [
                'value' => $this->mode?->value ?? (string) $this->mode,
                'label' => $this->mode?->label() ?? ucfirst((string) $this->mode),
                'color' => $this->mode?->badgeColor() ?? 'primary',
            ],
            'status'                 => [
                'value' => $this->status?->value ?? (string) $this->status,
                'label' => $this->status?->label() ?? ucfirst((string) $this->status),
                'color' => $this->status?->badgeColor() ?? 'grey',
            ],
            'client'                 => $this->swo?->client ? [
                'id'              => $this->swo->client->id,
                'commercial_name' => $this->swo->client->commercial_name,
                'legal_name'      => $this->swo->client->legal_name,
            ] : null,
            'service_site'           => $this->whenLoaded('serviceSite', fn () => [
                'id'   => $this->serviceSite->id,
                'name' => $this->serviceSite->name,
            ]),
            'team'                   => $this->whenLoaded('team', fn () => [
                'id'   => $this->team->id,
                'name' => $this->team->name,
            ]),
            'lead_technician'        => $this->whenLoaded('leadTechnician', fn () => [
                'id'   => $this->leadTechnician->id,
                'name' => $this->leadTechnician->first_name . ' ' . $this->leadTechnician->last_name,
            ]),
            'workshop_unit'          => $this->whenLoaded('workshopUnit', fn () => [
                'id'   => $this->workshopUnit->id,
                'name' => $this->workshopUnit->getTranslations('name'),
                'code' => $this->workshopUnit->code,
            ]),
            'planned_date'           => $this->planned_date?->format('Y-m-d'),
            'planned_start_time'     => $this->planned_start_time,
            'planned_duration_hours' => $this->planned_duration_hours,
            'actual_start_at'        => $this->actual_start_at?->toISOString(),
            'actual_end_at'          => $this->actual_end_at?->toISOString(),
            'total_interventions'    => $this->interventions_count ?? $this->interventions?->count() ?? 0,
            'created_at'             => $this->created_at?->toISOString(),
        ];
    }
}
