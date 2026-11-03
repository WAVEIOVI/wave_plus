<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkshopExitPassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                           => $this->id,
            'exit_pass_number'             => $this->exit_pass_number,
            'swo_id'                       => $this->swo_id,
            'swo_number'                   => $this->serviceWorkOrder?->swo_number,
            'mission_id'                   => $this->mission_id,
            'mission_number'               => $this->mission?->mission_number,
            'destination_workshop_unit_id' => $this->destination_workshop_unit_id,
            'destination_workshop_unit'    => $this->whenLoaded('destinationWorkshopUnit', fn () => [
                'id'   => $this->destinationWorkshopUnit->id,
                'name' => $this->destinationWorkshopUnit->getTranslations('name'),
                'code' => $this->destinationWorkshopUnit->code,
            ]),
            'status'                       => $this->status,
            'transfer_date'                => $this->transfer_date?->format('Y-m-d'),
            'reason'                       => $this->reason,
            'notes'                        => $this->notes,
            'responsible_employee_id'      => $this->responsible_employee_id,
            'responsible_employee'         => $this->whenLoaded('responsibleEmployee', fn () => [
                'id'         => $this->responsibleEmployee->id,
                'first_name' => $this->responsibleEmployee->first_name,
                'last_name'  => $this->responsibleEmployee->last_name,
                'full_name'  => trim($this->responsibleEmployee->first_name . ' ' . $this->responsibleEmployee->last_name),
            ]),
            'equipment'                    => $this->whenLoaded('equipment', fn () => $this->equipment->map(fn ($eq) => [
                'id'             => $eq->id,
                'equipment_code' => $eq->equipment_code,
                'serial_number'  => $eq->serial_number,
                'status'         => $eq->status,
                'condition'      => $eq->pivot->condition ?? null,
                'notes'          => $eq->pivot->notes ?? null,
            ])),
            'equipment_count'              => $this->relationLoaded('equipment') ? $this->equipment->count() : $this->exitPassEquipment()->count(),
            'created_at'                   => $this->created_at?->toISOString(),
        ];
    }
}
