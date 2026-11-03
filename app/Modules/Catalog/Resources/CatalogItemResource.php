<?php

namespace App\Modules\Catalog\Resources;

use App\Modules\BusinessPartners\Resources\SupplierResource;
use App\Modules\Catalog\Helpers\MediaFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Catalog\Models\CatalogItem
 */
class CatalogItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'item_type' => $this->item_type,
            'name' => $this->getTranslations('name'),
            'short_description' => $this->getTranslations('short_description'),
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'uom_id' => $this->uom_id,
            'status' => $this->status,
            'is_stockable' => $this->is_stockable,
            'is_purchasable' => $this->is_purchasable,
            'is_sellable' => $this->is_sellable,
            'current_stock' => $this->current_stock, // Remains since it hits stockItems relationship
            // Flat sub_type field — resolved from the extension relation that matches item_type.
            // The extension relations are selectively eager-loaded (id + type column only) in
            // the index query to avoid N+1 issues. In the show query all fields are loaded.
            'sub_type' => $this->when(true, function () {
                return match ($this->item_type) {
                    'product'      => $this->product?->product_type,
                    'consumable'   => $this->consumable?->consumable_type,
                    'eq_servicing' => $this->eqServicing?->eq_servicing_type,
                    default        => null,
                };
            }),

            'image' => $this->getFirstMediaUrl('images'),
            'document' => $this->getFirstMediaUrl('documents'),
            'certificate' => $this->getFirstMediaUrl('certificates'),

            // Full media collection arrays (only when the media relation is loaded,
            // i.e. in show() — not in the list index to keep payloads small).
            'media_images' => $this->when($this->relationLoaded('media'), fn () =>
                $this->getMedia('images')->map(fn ($m) => MediaFormatter::format($m))->values()
            ),
            'media_documents' => $this->when($this->relationLoaded('media'), fn () =>
                $this->getMedia('documents')->map(fn ($m) => MediaFormatter::format($m))->values()
            ),
            'media_certificates' => $this->when($this->relationLoaded('media'), fn () =>
                $this->getMedia('certificates')->map(fn ($m) => MediaFormatter::format($m))->values()
            ),
            'tags' => $this->tags,
            'certifications' => $this->certifications,

            'preferred_supplier_id' => $this->preferred_supplier_id,
            'warranty_months' => $this->warranty_months,
            'internal_notes' => $this->internal_notes,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

            // Relations
            'category' => new CatalogCategoryResource($this->whenLoaded('category')),
            'brand' => new CatalogBrandResource($this->whenLoaded('brand')),
            'uom' => new UnitOfMeasureResource($this->whenLoaded('uom')),
            'product' => $this->whenLoaded('product'),
            'consumable' => $this->whenLoaded('consumable'),
            'eq_servicing' => $this->whenLoaded('eqServicing'),
            'suppliers' => SupplierResource::collection($this->whenLoaded('suppliers')),
            'preferred_supplier' => new SupplierResource($this->whenLoaded('preferredSupplier')),
            'options' => CatalogOptionResource::collection($this->whenLoaded('options')),
            'variants' => CatalogVariantResource::collection($this->whenLoaded('variants')),

            // Computed helpers for front-end
            'price_range' => $this->when($this->relationLoaded('variants'), function () {
                if ($this->variants->isEmpty()) {
                    return null;
                }
                $min = $this->variants->min('price');
                $max = $this->variants->max('price');

                return $min == $max ? $min : "{$min} - {$max}";
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
