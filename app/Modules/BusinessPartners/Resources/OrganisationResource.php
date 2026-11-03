<?php

namespace App\Modules\BusinessPartners\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\BusinessPartners\Models\Organisation
 */
class OrganisationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organisation_id' => $this->organisation_id,
            'legal_name' => $this->getTranslations('legal_name'),
            'commercial_name' => $this->getTranslations('commercial_name'),
            'tax_id' => $this->tax_id,
            'rne_id' => $this->rne_id,
            'primary_phone' => $this->primary_phone,
            'primary_email' => $this->primary_email,
            'hq_address' => $this->getTranslations('hq_address'),
            'hq_city' => $this->getTranslations('hq_city'),
            'hq_postcode' => $this->hq_postcode,
            'hq_country' => $this->getTranslations('hq_country'),
            'account_manager_id' => $this->account_manager_id,
            'account_manager' => $this->whenLoaded('accountManager'),
            'partner_since' => $this->partner_since?->toDateString(),
            'organisation_type' => $this->organisation_type,
            'registration_number' => $this->registration_number,
            'registration_date' => $this->registration_date?->toDateString(),
            'accreditation_body' => $this->accreditation_body,
            'regulatory_authority' => $this->regulatory_authority,
            'parent_organisation' => $this->parent_organisation,
            'member_count' => $this->member_count,
            'scope' => $this->scope,
            'partnership_start_date' => $this->partnership_start_date?->toDateString(),
            'partnership_end_date' => $this->partnership_end_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->getTranslations('notes'),
            'logo_path' => $this->logo_path,
            'logo_url' => $this->getLogoUrl(),
            'logo_source' => $this->logo_source,
            'logo_domain' => $this->logo_domain,
            'logo_external_url' => $this->logo_external_url,
            'addresses' => BpAddressResource::collection($this->whenLoaded('addresses')),
            'contacts' => BpContactResource::collection($this->whenLoaded('contacts')),
            'bank_accounts' => BpBankAccountResource::collection($this->whenLoaded('bankAccounts')),
            'vat_certificates' => BpVatCertificateResource::collection($this->whenLoaded('vatCertificates')),
            'documents' => BpDocumentResource::collection($this->whenLoaded('documents')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
