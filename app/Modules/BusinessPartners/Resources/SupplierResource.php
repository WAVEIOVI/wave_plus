<?php

namespace App\Modules\BusinessPartners\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\BusinessPartners\Models\Supplier
 */
class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'legal_name' => $this->getTranslations('legal_name'),
            'commercial_name' => $this->getTranslations('commercial_name'),
            'tax_id' => $this->tax_id,
            'rne_id' => $this->rne_id,
            'legal_status' => $this->legal_status,
            'company_size' => $this->company_size,
            'industry' => $this->industry,
            'founding_year' => $this->founding_year,
            'website' => $this->website,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->getLogoUrl(),
            'logo_source' => $this->logo_source,
            'logo_domain' => $this->logo_domain,
            'logo_external_url' => $this->logo_external_url,
            'primary_phone' => $this->primary_phone,
            'primary_email' => $this->primary_email,
            'hq_address' => $this->getTranslations('hq_address'),
            'hq_city' => $this->getTranslations('hq_city'),
            'hq_postcode' => $this->hq_postcode,
            'hq_country' => $this->getTranslations('hq_country'),
            'account_manager_id' => $this->account_manager_id,
            'account_manager' => $this->whenLoaded('accountManager'),
            'partner_since' => $this->partner_since?->toDateString(),
            'satisfaction_score' => $this->satisfaction_score,
            'supplier_category' => $this->supplier_category,
            'lead_time_days' => $this->lead_time_days,
            'payment_terms' => $this->payment_terms,
            'minimum_order_value' => $this->minimum_order_value,
            'status' => $this->status,
            'notes' => $this->getTranslations('notes'),
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

    /**
     * Get the logo URL, ensuring it uses the current request's base URL
     * instead of APP_URL (which may not match the dev server's port).
     */
    public function getLogoUrl(): ?string
    {
        $mediaUrl = $this->getFirstMediaUrl('logo');

        if ($mediaUrl) {
            // Replace the APP_URL base with the current request's base URL
            $appUrl = rtrim(config('app.url'), '/');
            $requestBase = rtrim(url('/'), '/');

            if ($appUrl !== $requestBase) {
                return str_replace($appUrl, $requestBase, $mediaUrl);
            }

            return $mediaUrl;
        }

        // Provider-hosted logo (Brandfetch / Logo.dev) is hotlinked, never
        // re-hosted, so it takes precedence over the legacy logo_path column.
        if ($this->logo_external_url) {
            return $this->logo_external_url;
        }

        // Fallback to logo_path (legacy column)
        if ($this->logo_path) {
            return url($this->logo_path);
        }

        return null;
    }
}
