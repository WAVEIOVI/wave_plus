<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkshopReceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'reception_number'     => $this->reception_number,
            'workshop_unit_id'     => $this->workshop_unit_id,
            'workshop_unit'        => $this->whenLoaded('workshopUnit', fn () => [
                'id'   => $this->workshopUnit->id,
                'name' => $this->workshopUnit->getTranslations('name'),
                'code' => $this->workshopUnit->code,
            ]),
            'equipment_id'         => $this->equipment_id,
            'equipment'            => $this->whenLoaded('equipment', fn () => [
                'id'             => $this->equipment->id,
                'equipment_code' => $this->equipment->equipment_code,
                'serial_number'  => $this->equipment->serial_number,
                'status'         => $this->equipment->status,
            ]),
            'sales_order_id'       => $this->sales_order_id,
            'swo_id'               => $this->swo_id,
            'swo_number'           => $this->swo?->swo_number,
            'source_mission_id'    => $this->source_mission_id,
            'source_mission_number' => $this->relationLoaded('sourceMission') ? $this->sourceMission?->mission_number : null,
            'exit_pass_id'         => $this->exit_pass_id,
            'exit_pass_number'     => $this->relationLoaded('exitPass') ? $this->exitPass?->exit_pass_number : null,
            'client_id'            => $this->client_id,
            'client'               => $this->whenLoaded('client', fn () => [
                'id'              => $this->client->id,
                'legal_name'      => $this->client->legal_name,
                'commercial_name' => $this->client->commercial_name,
            ]),
            'received_by'          => $this->received_by,
            'receiver'             => $this->whenLoaded('receiver', fn () => [
                'id'   => $this->receiver->id,
                'name' => $this->receiver->name,
            ]),
            'reception_date'       => $this->reception_date?->format('Y-m-d'),
            'condition'            => $this->condition,
            'visible_damages'      => $this->visible_damages,
            'accessories_received' => $this->accessories_received,
            'notes'                => $this->notes,
            'status'               => [
                'value' => $this->status?->value ?? (string) $this->status,
                'label' => $this->status?->label() ?? ucfirst((string) $this->status),
                'color' => $this->status?->badgeColor() ?? 'info',
            ],
            'created_at'           => $this->created_at?->toISOString(),
        ];
    }
}
