<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Resources;

use App\Modules\BusinessPartners\Resources\ClientResource;
use App\Modules\Catalog\Helpers\MediaFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin \App\Modules\ClientAssets\Models\ClientSite
 */
class ClientSiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $photos = $this->whenLoaded('media', function () {
            return $this->getMedia('photos')->map(function (Media $m) {
                $formatted = MediaFormatter::format($m);
                $formatted['is_main'] = (bool) $m->getCustomProperty('is_main');
                $formatted['title'] = $m->getCustomProperty('title') ?: $m->file_name;

                return $formatted;
            });
        });

        $documents = $this->whenLoaded('media', function () {
            return $this->getMedia('documents')->map(function (Media $m) {
                $formatted = MediaFormatter::format($m);
                $formatted['category'] = $m->getCustomProperty('category') ?: 'general';
                $formatted['title'] = $m->getCustomProperty('title') ?: $m->file_name;
                $formatted['notes'] = $m->getCustomProperty('notes');
                $formatted['uploaded_by'] = $m->getCustomProperty('uploaded_by');

                return $formatted;
            });
        });

        $sitePlans = $this->whenLoaded('media', function () {
            return $this->getMedia('site_plans')->map(function (Media $m) {
                $formatted = MediaFormatter::format($m);
                $formatted['title'] = $m->getCustomProperty('title') ?: $m->file_name;

                return $formatted;
            });
        });

        $mainPhoto = $this->relationLoaded('media') ? $this->getMainPhoto() : null;

        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'bp_address_id' => $this->bp_address_id,
            'site_code' => $this->site_code,
            'name' => $this->name,
            'description' => $this->description,
            'site_type' => $this->site_type,
            'status' => $this->status,
            'risk_class' => $this->risk_class,
            'operating_hours' => $this->operating_hours,
            'access_instructions' => $this->access_instructions,
            'safety_instructions' => $this->safety_instructions,
            'special_instructions' => $this->special_instructions,
            'opened_at' => $this->opened_at?->toDateString(),
            'closed_at' => $this->closed_at?->toDateString(),
            'notes' => $this->notes,

            // Relations
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'client_id' => $this->client->client_id ?? null,
                'code' => $this->client->client_id ?? $this->client->code ?? null,
                'legal_name' => $this->client->legal_name,
                'commercial_name' => $this->client->commercial_name,
                'trade_name' => $this->client->commercial_name ?? $this->client->trade_name ?? null,
                'tax_id' => $this->client->tax_id,
                'industry' => $this->client->industry,
                'primary_phone' => $this->client->primary_phone,
                'primary_email' => $this->client->primary_email,
                'logo_url' => (new ClientResource($this->client))->getLogoUrl(),
                'logo_source' => $this->client->logo_source ?? null,
                'logo_domain' => $this->client->logo_domain ?? null,
            ]),
            'address' => new BpAddressResource($this->whenLoaded('address')),
            'areas' => ClientSiteAreaResource::collection($this->whenLoaded('areas')),
            'root_areas' => ClientSiteAreaResource::collection($this->whenLoaded('rootAreas')),

            // Counts
            'areas_count' => $this->whenCounted('areas'),
            'equipment_count' => $this->whenCounted('equipment'),

            // Media
            'main_photo_url' => $mainPhoto ? MediaFormatter::format($mainPhoto)['url'] : null,
            'photos' => $photos,
            'documents' => $documents,
            'site_plans' => $sitePlans,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
