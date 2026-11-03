<?php

namespace App\Modules\ClientAssets\Resources;

use App\Modules\Catalog\Resources\CatalogItemResource;
use App\Modules\ClientAssets\Support\WorkwearStudioTemplateCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkwearModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $effectiveSilhouette = $this->silhouette ?: ($this->garment_types[0] ?? 'coverall');
        $template = WorkwearStudioTemplateCatalog::getTemplate($effectiveSilhouette);

        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'catalog_item_id' => $this->catalog_item_id,
            'model_ref' => $this->model_ref,
            'name' => $this->name,
            'garment_types' => $this->garment_types,
            'fabric' => $this->fabric,
            'fabric_id' => $this->fabric_id,
            'colors' => $this->colors,
            'color_regions' => WorkwearStudioTemplateCatalog::resolveColorRegions($effectiveSilhouette, $this->colors),
            'studio_scale' => $template['scale'],
            'custom_type' => $this->custom_type,
            'silhouette_category' => $this->silhouette_category,
            'silhouette_subcategory' => $this->silhouette_subcategory,
            'silhouette' => $this->silhouette,
            'silhouette_item' => $this->silhouette_item,
            'size_range' => $this->size_range,
            'technical_specs' => $this->technical_specs,
            'custom_made_requirements' => $this->custom_made_requirements,
            'print_text' => $this->print_text,
            'print_type' => $this->print_type,
            'logo_placement' => $this->logo_placement,
            'logo_ged_ref' => $this->logo_ged_ref,
            'en_standards' => $this->en_standards,
            'fr_rating' => $this->fr_rating,
            'waterproofing' => $this->waterproofing,
            'certifications' => $this->certifications ?? [],
            'standards' => $this->standards ?? [],
            'supplier_id' => $this->supplier_id,
            'notes' => $this->notes,
            'status' => $this->status,

            'client' => $this->whenLoaded('client', fn () => ['id' => $this->client->id, 'legal_name' => $this->client->legal_name]),
            'supplier' => $this->whenLoaded('supplier', fn () => ['id' => $this->supplier->id, 'legal_name' => $this->supplier->legal_name]),
            'workwear_fabric' => new WorkwearFabricResource($this->whenLoaded('workwearFabric')),
            'catalog_item' => new CatalogItemResource($this->whenLoaded('catalogItem')),
            'versions' => WorkwearModelVersionResource::collection($this->whenLoaded('versions')),
            'options' => WorkwearModelOptionResource::collection($this->whenLoaded('options')),

            'items' => WorkwearItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'available_items_count' => $this->when(isset($this->resource->available_items_count), fn () => (int) $this->resource->available_items_count),
            'placements' => WorkwearModelPlacementResource::collection($this->whenLoaded('placements')),

            // Media
            'logos' => $this->when($this->relationLoaded('media'), fn () => $this->getMedia('logos')->map(fn ($m) => [
                'id' => $m->id,
                'url' => $m->getUrl(),
                'thumb' => $m->hasGeneratedConversion('thumb') ? $m->getUrl('thumb') : $m->getUrl(),
                'thumb_url' => $m->hasGeneratedConversion('thumb') ? $m->getUrl('thumb') : $m->getUrl(),
                'name' => $m->file_name,
                'file_name' => $m->file_name,
                'title' => $m->getCustomProperty('title') ?: $m->file_name,
                'notes' => $m->getCustomProperty('notes'),
                'size' => $m->size,
                'human_raw_size' => $m->human_readable_size,
                'mime_type' => $m->mime_type,
                'custom_properties' => $m->custom_properties ?? [],
                'download_url' => url("/api/client-assets/workwear-models/{$this->id}/media/{$m->id}/download"),
                'created_at' => $m->created_at?->toISOString(),
            ])),
            'design_files' => $this->when($this->relationLoaded('media'), fn () => $this->getMedia('design_files')->map(fn ($m) => [
                'id' => $m->id,
                'url' => url("/api/client-assets/workwear-models/{$this->id}/media/{$m->id}/download"),
                'download_url' => url("/api/client-assets/workwear-models/{$this->id}/media/{$m->id}/download"),
                'name' => $m->file_name,
                'file_name' => $m->file_name,
                'title' => $m->getCustomProperty('title') ?: $m->file_name,
                'category' => $m->getCustomProperty('category') ?: $m->getCustomProperty('type') ?: 'design_spec',
                'type' => $m->getCustomProperty('type') ?: $m->getCustomProperty('category') ?: 'design_spec',
                'reference' => $m->getCustomProperty('reference'),
                'notes' => $m->getCustomProperty('notes'),
                'size' => $m->size,
                'human_raw_size' => $m->human_readable_size,
                'mime_type' => $m->mime_type,
                'custom_properties' => $m->custom_properties ?? [],
                'is_private' => true,
                'created_at' => $m->created_at?->toISOString(),
            ])),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
