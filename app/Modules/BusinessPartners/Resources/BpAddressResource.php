<?php

namespace App\Modules\BusinessPartners\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\BusinessPartners\Models\BpAddress
 */
class BpAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'addressable_type' => $this->addressable_type,
            'addressable_id' => $this->addressable_id,
            'type' => $this->type,
            'is_primary' => $this->is_primary,
            'is_billing_default' => $this->is_billing_default,
            'is_shipping_default' => $this->is_shipping_default,
            'label' => $this->getTranslations('label'),
            'address_street' => $this->getTranslations('address_street'),
            'address_street2' => $this->getTranslations('address_street2'),
            'city' => $this->getTranslations('city'),
            'state' => $this->getTranslations('state'),
            'postcode' => $this->postcode,
            'country' => $this->getTranslations('country'),
            'phone' => $this->phone,
            'email' => $this->email,
            'responsible' => $this->responsible,
            'nearest_fire_station' => $this->nearest_fire_station,
            'work_team' => $this->work_team,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'notes' => $this->getTranslations('notes'),
            'contacts' => BpContactResource::collection($this->whenLoaded('contacts')),
            'client_sites' => $this->whenLoaded('clientSites', function () {
                return $this->clientSites->map(fn ($site) => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'code' => $site->site_code,
                    'status' => $site->status,
                    'site_type' => $site->site_type,
                ]);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
