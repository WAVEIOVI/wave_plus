<?php

namespace App\Modules\BusinessPartners\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\BusinessPartners\Models\BpVatCertificate
 */
class BpVatCertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certifiable_type' => $this->certifiable_type,
            'certifiable_id' => $this->certifiable_id,
            'certificate_number' => $this->certificate_number,
            'issuing_authority' => $this->issuing_authority,
            'issue_date' => $this->issue_date?->toDateString(),
            'expiration_date' => $this->expiration_date?->toDateString(),
            'status' => $this->status,
            'document_path' => $this->document_path,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
