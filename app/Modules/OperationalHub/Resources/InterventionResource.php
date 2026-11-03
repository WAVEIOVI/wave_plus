<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterventionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'mission_id'             => $this->mission_id,
            'equipment_id'           => $this->equipment_id,
            'equipment'              => $this->whenLoaded('equipment', fn () => [
                'id'             => $this->equipment->id,
                'equipment_code' => $this->equipment->equipment_code,
                'serial_number'  => $this->equipment->serial_number,
                'status'         => $this->equipment->status,
            ]),
            'blueprint_id'           => $this->blueprint_id,
            'blueprint'              => $this->whenLoaded('blueprint', fn () => [
                'id'   => $this->blueprint->id,
                'name' => $this->blueprint->getTranslations('name'),
                'code' => $this->blueprint->code,
            ]),
            'service_type'           => $this->service_type,
            'status'                 => $this->status,
            'result'                 => $this->result,
            'technician_notes'       => $this->technician_notes,
            'technical_observations' => $this->technical_observations,
            'measurements'           => $this->measurements,
            'started_at'             => $this->started_at?->toISOString(),
            'completed_at'           => $this->completed_at?->toISOString(),
            'materials'              => $this->whenLoaded('materials', fn () => $this->materials->map(fn ($m) => [
                'id'                   => $m->id,
                'catalog_item_id'      => $m->catalog_item_id,
                'catalog_variant_id'   => $m->catalog_variant_id,
                'variant_sku_snapshot' => $m->variant_sku_snapshot,
                'description_snapshot' => $m->description_snapshot,
                'qty_used'             => (float) $m->qty_used,
                'uom_code_snapshot'    => $m->uom_code_snapshot,
                'notes'                => $m->notes,
            ])),
            'findings'               => FindingResource::collection($this->whenLoaded('findings')),
            'created_at'             => $this->created_at?->toISOString(),
        ];
    }
}
