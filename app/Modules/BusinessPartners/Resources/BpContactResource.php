<?php

namespace App\Modules\BusinessPartners\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\BusinessPartners\Models\BpContact
 */
class BpContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contactable_type' => $this->contactable_type,
            'contactable_id' => $this->contactable_id,
            'first_name' => $this->getTranslations('first_name'),
            'last_name' => $this->getTranslations('last_name'),
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'avatar_path' => $this->avatar_path,
            'job_title' => $this->job_title,
            'department' => $this->department,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'whatsapp_number' => $this->whatsapp_number,
            'email' => $this->email,
            'linkedin_url' => $this->linkedin_url,
            'is_primary' => $this->is_primary,
            'is_decision_maker' => $this->is_decision_maker,
            'status' => $this->status,
            'notes' => $this->getTranslations('notes'),
            'addresses' => BpAddressResource::collection($this->whenLoaded('addresses')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
