<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProofOfDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'dwo_id'            => $this->dwo_id,
            'delivered_at'      => $this->delivered_at?->toISOString(),
            'receiver_name'     => $this->receiver_name,
            'receiver_title'    => $this->receiver_title,
            'signature_data'    => $this->signature_data,
            'has_signature'     => !empty($this->signature_data),
            'signed_at'         => $this->signed_at?->toISOString(),
            'condition_overall' => [
                'value' => $this->condition_overall?->value ?? (string) $this->condition_overall,
                'label' => $this->condition_overall?->label() ?? (string) $this->condition_overall,
                'color' => $this->condition_overall?->badgeColor() ?? 'grey',
            ],
            'notes'             => $this->notes,
            'latitude'          => $this->latitude,
            'longitude'         => $this->longitude,
            'created_by'        => $this->whenLoaded('creator', fn () => [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at'        => $this->created_at?->toISOString(),
        ];
    }
}
