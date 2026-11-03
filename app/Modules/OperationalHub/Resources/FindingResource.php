<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FindingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'findable_type'              => $this->findable_type,
            'findable_id'                => $this->findable_id,
            'equipment_id'               => $this->equipment_id,
            'site_id'                    => $this->site_id,
            'category'                   => $this->category,
            'type'                       => [
                'value' => $this->type?->value ?? (string) $this->type,
                'label' => $this->type?->label() ?? ucfirst((string) $this->type),
                'color' => $this->type?->badgeColor() ?? 'info',
            ],
            'title'                      => $this->title,
            'description'                => $this->description,
            'severity'                   => $this->severity,
            'recommendation'             => $this->recommendation,
            'corrective_action'          => $this->corrective_action,
            'requires_workshop_transfer' => (bool) $this->requires_workshop_transfer,
            'status'                     => $this->status,
            'assigned_to'                => $this->assigned_to,
            'resolved_at'                => $this->resolved_at?->toISOString(),
            'creator'                    => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at'                 => $this->created_at?->toISOString(),
        ];
    }
}
