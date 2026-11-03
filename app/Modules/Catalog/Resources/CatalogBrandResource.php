<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Catalog\Models\CatalogBrand
 */
class CatalogBrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'country_of_origin' => $this->country_of_origin,
            'warranty_policy_notes' => $this->warranty_policy_notes,
            'logo_source' => $this->logo_source,
            'logo_domain' => $this->logo_domain,
            'logo_external_url' => $this->logo_external_url,
            'logo_url' => $this->getLogoUrl(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
