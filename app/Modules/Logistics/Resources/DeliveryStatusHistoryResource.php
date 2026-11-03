<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'dwo_id'      => $this->dwo_id,
            'from_status' => $this->from_status ? [
                'value' => $this->from_status->value,
                'label' => $this->from_status->label(),
                'color' => $this->from_status->badgeColor(),
            ] : null,
            'to_status'   => [
                'value' => $this->to_status?->value ?? (string) $this->to_status,
                'label' => $this->to_status?->label() ?? ucfirst((string) $this->to_status),
                'color' => $this->to_status?->badgeColor() ?? 'grey',
            ],
            'user'        => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'changed_at'  => $this->changed_at?->toISOString(),
            'notes'       => $this->notes,
            'metadata'    => $this->metadata,
        ];
    }
}
