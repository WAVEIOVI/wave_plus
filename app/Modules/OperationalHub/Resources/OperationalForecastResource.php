<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationalForecastResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'title'                 => $this->title,
            'client_id'             => $this->client_id,
            'client'                => $this->whenLoaded('client', fn () => [
                'id'              => $this->client->id,
                'commercial_name' => $this->client->commercial_name,
                'legal_name'      => $this->client->legal_name,
            ]),
            'equipment_id'          => $this->equipment_id,
            'equipment'             => $this->whenLoaded('equipment', fn () => [
                'id'             => $this->equipment->id,
                'equipment_code' => $this->equipment->equipment_code,
            ]),
            'service_site_id'       => $this->service_site_id,
            'service_site'          => $this->whenLoaded('serviceSite', fn () => [
                'id'   => $this->serviceSite->id,
                'name' => $this->serviceSite->name,
            ]),
            'forecast_type'         => $this->forecast_type,
            'expected_date'         => $this->expected_date?->format('Y-m-d'),
            'estimated_hours'       => (float) $this->estimated_hours,
            'estimated_technicians' => $this->estimated_technicians,
            'priority'              => $this->priority,
            'notes'                 => $this->notes,
            'status'                => $this->status,
            'converted_swo_id'      => $this->converted_swo_id,
            'converted_swo_number'  => $this->convertedSwo?->swo_number,
            'created_at'            => $this->created_at?->toISOString(),
        ];
    }
}
