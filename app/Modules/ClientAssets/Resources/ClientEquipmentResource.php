<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Resources;

use App\Modules\Catalog\Helpers\MediaFormatter;
use App\Modules\Catalog\Resources\CatalogBrandResource;
use App\Modules\Catalog\Resources\CatalogItemResource;
use App\Modules\Catalog\Resources\CatalogVariantResource;
use App\Modules\Catalog\Resources\EquipmentClassResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin \App\Modules\ClientAssets\Models\ClientEquipment
 */
class ClientEquipmentResource extends JsonResource
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

        $certificates = $this->whenLoaded('media', function () {
            return $this->getMedia('certificates')->map(function (Media $m) {
                $formatted = MediaFormatter::format($m);
                $formatted['title'] = $m->getCustomProperty('title') ?: $m->file_name;
                $formatted['category'] = $m->getCustomProperty('category') ?: 'certificate';

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

        $mainPhoto = $this->relationLoaded('media') ? $this->getMainPhoto() : null;

        return [
            'id' => $this->id,
            'equipment_code' => $this->equipment_code,
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'site_id' => $this->site_id,
            'area_id' => $this->area_id,
            'equipment_class_id' => $this->equipment_class_id,
            'blueprint_id' => $this->blueprint_id,
            'catalog_item_id' => $this->catalog_item_id,
            'catalog_variant_id' => $this->catalog_variant_id,
            'brand_id' => $this->brand_id,
            'brand' => $this->brand,
            'model_name' => $this->model_name,
            'serial_number' => $this->serial_number,
            'internal_number' => $this->internal_number,
            'manufacturer_part_number' => $this->manufacturer_part_number,
            'asset_tag' => $this->asset_tag,
            'manufacture_date' => $this->manufacture_date?->toDateString(),
            'install_date' => $this->install_date?->toDateString(),
            'commissioning_date' => $this->commissioning_date?->toDateString(),
            'decommissioning_date' => $this->decommissioning_date?->toDateString(),
            'warranty_start_date' => $this->warranty_start_date?->toDateString(),
            'warranty_end_date' => $this->warranty_end_date?->toDateString(),
            'last_inspection_date' => $this->last_inspection_date?->toDateString(),
            'next_inspection_date' => $this->next_inspection_date?->toDateString(),
            'inspection_freq_months' => $this->inspection_freq_months,
            'status' => $this->status,
            'lifecycle_status' => $this->lifecycle_status,
            'compliance_status' => $this->compliance_status,
            'condition_status' => $this->condition_status,
            'criticality' => $this->criticality,
            'description' => $this->description,
            'specs' => $this->specs,
            'certifications' => $this->certifications,
            'notes' => $this->notes,

            // Relations
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'legal_name' => $this->client->legal_name,
                'commercial_name' => $this->client->commercial_name,
                'trade_name' => $this->client->commercial_name,
                'code' => $this->client->code ?? null,
            ]),
            'site' => $this->whenLoaded('site', fn () => [
                'id' => $this->site->id,
                'name' => $this->site->name,
                'site_code' => $this->site->site_code,
                'address' => $this->site->relationLoaded('address') && $this->site->address ? [
                    'id' => $this->site->address->id,
                    'address_street' => $this->site->address->address_street,
                    'city' => $this->site->address->city,
                ] : null,
            ]),
            'area' => $this->whenLoaded('area', fn () => [
                'id' => $this->area->id,
                'name' => $this->area->name,
                'code' => $this->area->code,
                'area_type' => $this->area->area_type,
                'hierarchy_path' => $this->area->hierarchy_path,
            ]),
            'equipment_class' => new EquipmentClassResource($this->whenLoaded('equipmentClass')),
            'blueprint' => $this->whenLoaded('blueprint', fn () => [
                'id' => $this->blueprint->id,
                'code' => $this->blueprint->code,
                'name' => $this->blueprint->getTranslations('name'),
                'working_pressure_bar' => $this->blueprint->working_pressure_bar,
                'fire_class_rating' => $this->blueprint->fire_class_rating,
                'shelf_life_years' => $this->blueprint->shelf_life_years,
            ]),
            'catalog_item' => new CatalogItemResource($this->whenLoaded('catalogItem')),
            'catalog_variant' => new CatalogVariantResource($this->whenLoaded('catalogVariant')),
            'catalog_brand' => new CatalogBrandResource($this->whenLoaded('catalogBrand')),

            // EAM Snapshots & Histories
            'current_qr_code' => $this->whenLoaded('currentQrCode', fn () => [
                'id' => $this->currentQrCode->id,
                'qr_code' => $this->currentQrCode->qr_code,
                'assigned_at' => $this->currentQrCode->assigned_at?->toISOString(),
            ]),
            'qr_codes' => $this->whenLoaded('qrCodes', fn () => $this->qrCodes->map(fn ($qr) => [
                'id' => $qr->id,
                'qr_code' => $qr->qr_code,
                'is_current' => $qr->is_current,
                'assigned_at' => $qr->assigned_at?->toISOString(),
                'unassigned_at' => $qr->unassigned_at?->toISOString(),
                'reason' => $qr->reason,
                'notes' => $qr->notes,
            ])),
            'intervention_cycles' => $this->whenLoaded('interventionCycles', fn () => $this->interventionCycles->map(fn ($c) => [
                'id' => $c->id,
                'source_blueprint_cycle_id' => $c->source_blueprint_cycle_id,
                'cycle_type' => $c->cycle_type,
                'interval_value' => $c->interval_value,
                'interval_unit' => $c->interval_unit,
                'last_intervention_date' => $c->last_intervention_date?->toDateString(),
                'next_due_date' => $c->next_due_date?->toDateString(),
                'enabled' => $c->enabled,
                'notes' => $c->notes,
            ])),
            'location_histories' => $this->whenLoaded('locationHistories', fn () => $this->locationHistories->map(fn ($lh) => [
                'id' => $lh->id,
                'client_id' => $lh->client_id,
                'site_id' => $lh->site_id,
                'site_name' => $lh->site?->name,
                'area_id' => $lh->area_id,
                'area_name' => $lh->area?->name,
                'area_path' => $lh->area?->hierarchy_path,
                'started_at' => $lh->started_at?->toISOString(),
                'ended_at' => $lh->ended_at?->toISOString(),
                'movement_type' => $lh->movement_type,
                'reason' => $lh->reason,
                'notes' => $lh->notes,
                'changer' => $lh->changer ? ['id' => $lh->changer->id, 'name' => $lh->changer->name] : null,
            ])),
            'status_histories' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($sh) => [
                'id' => $sh->id,
                'status' => $sh->status,
                'started_at' => $sh->started_at?->toISOString(),
                'ended_at' => $sh->ended_at?->toISOString(),
                'reason' => $sh->reason,
                'notes' => $sh->notes,
                'changer' => $sh->changer ? ['id' => $sh->changer->id, 'name' => $sh->changer->name] : null,
            ])),
            'option_values' => $this->whenLoaded('optionValues', fn () => $this->optionValues->map(fn ($ov) => [
                'id' => $ov->id,
                'blueprint_option_id' => $ov->blueprint_option_id,
                'option_name' => $ov->option_name ?: ($ov->blueprintOption ? $ov->blueprintOption->getTranslation('name', 'en') : null),
                'option_value_id' => $ov->option_value_id,
                'value_text' => $ov->value_text,
                'value_number' => $ov->value_number,
                'value_boolean' => $ov->value_boolean,
                'value_date' => $ov->value_date?->toDateString(),
                'display_value' => $ov->display_value,
            ])),
            'compliance_records' => $this->whenLoaded('complianceRecords', fn () => $this->complianceRecords->map(fn ($cr) => [
                'id' => $cr->id,
                'compliance_type' => $cr->compliance_type,
                'status' => $cr->status,
                'valid_from' => $cr->valid_from?->toDateString(),
                'valid_until' => $cr->valid_until?->toDateString(),
                'reference_number' => $cr->reference_number,
                'document_id' => $cr->document_id,
                'notes' => $cr->notes,
            ])),
            'transfers' => ClientEquipmentTransferResource::collection($this->whenLoaded('transfers')),

            // Media
            'main_photo_url' => $mainPhoto ? MediaFormatter::format($mainPhoto)['url'] : null,
            'photos' => $photos,
            'certificates' => $certificates,
            'documents' => $documents,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
