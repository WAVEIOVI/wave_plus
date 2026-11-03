<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceDeliveryNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'sdn_number'         => $this->sdn_number,
            'swo_id'             => $this->swo_id,
            'swo_number'         => $this->swo?->swo_number,
            'mission_id'         => $this->mission_id,
            'mission_number'     => $this->mission?->mission_number,
            'client_id'          => $this->client_id,
            'client'             => $this->whenLoaded('client', fn () => [
                'id'              => $this->client->id,
                'legal_name'      => $this->client->legal_name,
                'commercial_name' => $this->client->commercial_name,
            ]),
            'service_site_id'    => $this->service_site_id,
            'service_site'       => $this->whenLoaded('serviceSite', fn () => [
                'id'   => $this->serviceSite->id,
                'name' => $this->serviceSite->name,
            ]),
            'issued_date'        => $this->issued_date?->format('Y-m-d'),
            'period_start'       => $this->period_start?->format('Y-m-d'),
            'period_end'         => $this->period_end?->format('Y-m-d'),
            'issued_by'          => $this->issuer?->name,
            'signed_at'          => $this->signed_at?->toISOString(),
            'client_signer_name' => $this->client_signer_name,
            'client_signature'   => $this->client_signature,
            'status'             => $this->status,
            'notes'              => $this->notes,
            'lines'              => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => [
                'id'              => $l->id,
                'intervention_id' => $l->intervention_id,
                'equipment_id'    => $l->equipment_id,
                'equipment_code'  => $l->equipment?->equipment_code,
                'description'     => $l->description,
                'service_type'    => $l->service_type,
                'result'          => $l->result,
                'technician_name' => $l->technician_name,
                'documented_at'   => $l->documented_at?->toISOString(),
            ])),
            'created_at'         => $this->created_at?->toISOString(),
        ];
    }
}
