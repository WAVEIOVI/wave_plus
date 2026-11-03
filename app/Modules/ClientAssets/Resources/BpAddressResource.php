<?php

namespace App\Modules\ClientAssets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Address resource for embedding in ClientSite responses.
 */
class BpAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type ?? 'operational_site',
            'is_primary' => (bool) ($this->is_primary ?? false),
            'is_billing_default' => (bool) ($this->is_billing_default ?? false),
            'is_shipping_default' => (bool) ($this->is_shipping_default ?? false),
            'label' => $this->label,
            'address_street' => $this->address_street,
            'address_street2' => $this->address_street2,
            'city' => $this->city,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'country' => $this->country ?? 'Tunisia',
            'phone' => $this->phone,
            'email' => $this->email,
            'responsible' => $this->responsible,
            'nearest_fire_station' => $this->nearest_fire_station,
            'work_team' => $this->work_team,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'notes' => $this->notes,
            'contacts' => $this->whenLoaded('contacts', function () {
                return $this->contacts->map(function ($contact) {
                    return [
                        'id' => $contact->id,
                        'first_name' => $contact->first_name,
                        'last_name' => $contact->last_name,
                        'full_name' => $contact->full_name,
                        'job_title' => $contact->job_title,
                        'department' => $contact->department,
                        'phone' => $contact->phone,
                        'mobile' => $contact->mobile,
                        'email' => $contact->email,
                        'is_primary' => (bool) ($contact->is_primary ?? false),
                        'is_primary_location' => (bool) ($contact->pivot->is_primary_location ?? false),
                    ];
                });
            }),
        ];
    }
}

