<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'dwo_id'           => $this->dwo_id,
            'dwo_line_id'      => $this->dwo_line_id,
            'reason'           => [
                'value' => $this->reason?->value ?? (string) $this->reason,
                'label' => $this->reason?->label() ?? (string) $this->reason,
            ],
            'affected_qty'     => (float) ($this->affected_qty ?? 0),
            'notes'            => $this->notes,
            'resolution_notes' => $this->resolution_notes,
            'status'           => $this->status,
            'resolved_at'      => $this->resolved_at?->toISOString(),
            'resolver'         => $this->whenLoaded('resolver', fn () => [
                'id'   => $this->resolver->id,
                'name' => $this->resolver->name,
            ]),
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}
