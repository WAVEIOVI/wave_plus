<?php

namespace App\Modules\ClientAssets\Requests\Concerns;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\ClientAssets\Enums\WorkwearPlacementType;
use App\Modules\ClientAssets\Enums\WorkwearPlacementZone;
use App\Modules\ClientAssets\Enums\WorkwearSilhouette;
use App\Modules\ClientAssets\Models\WorkwearModel;
use App\Modules\ClientAssets\Support\WorkwearStudioTemplateCatalog;
use Illuminate\Validation\Rule;

/**
 * Shared validation for the Workwear Model "studio" payload: garment illustration,
 * size range, technical specifications and customization placements.
 */
trait WorkwearModelStudioRules
{
    public const TECHNICAL_SPEC_KEYS = [
        'fastening',
        'reinforcements',
        'storage',
        'care',
        'ergonomics',
        'adjustability',
        'visibility',
        'collar',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function studioRules(): array
    {
        $rules = [
            'catalog_item_id' => 'nullable|ulid|exists:catalog_items,id',
            'fabric_id' => 'nullable|ulid|exists:workwear_fabrics,id',
            'silhouette' => ['nullable', Rule::in(WorkwearSilhouette::values())],
            'silhouette_category' => 'nullable|string|max:100',
            'silhouette_subcategory' => 'nullable|string|max:100',
            'silhouette_item' => 'nullable|string|max:150',
            'size_range' => 'nullable|array|max:30',
            'size_range.*' => 'required|string|max:20|distinct',
            'technical_specs' => 'nullable|array',
            'certifications' => 'nullable|array|max:40',
            'certifications.*' => 'required|string|max:150',
            'standards' => 'nullable|array|max:40',
            'standards.*' => 'required|string|max:150',
            'custom_made_requirements' => 'nullable|array',
            'custom_made_requirements.is_custom_made' => 'nullable|boolean',
            'custom_made_requirements.fit_variation' => 'nullable|string|max:100',
            'custom_made_requirements.special_sizing' => 'nullable|string|max:500',
            'custom_made_requirements.construction_requirements' => 'nullable|array|max:30',
            'custom_made_requirements.construction_requirements.*' => 'required|string|max:200',
            'custom_made_requirements.lead_time_days' => 'nullable|integer|min:0|max:365',
            'custom_made_requirements.moq' => 'nullable|integer|min:1|max:100000',
            'custom_made_requirements.notes' => 'nullable|string|max:2000',
            'change_summary' => 'nullable|string|max:500',

            'placements' => 'nullable|array|max:60',
            'placements.*.id' => 'nullable|ulid',
            'placements.*.view' => ['required', Rule::in(['front', 'back'])],
            'placements.*.type' => ['required', Rule::in(WorkwearPlacementType::values())],
            'placements.*.label' => 'required|string|max:120',
            'placements.*.zone' => ['required', Rule::in(WorkwearPlacementZone::values())],
            'placements.*.text' => 'nullable|string|max:255',
            'placements.*.method' => 'nullable|string|max:80',
            'placements.*.color' => 'nullable|string|max:30',
            'placements.*.width_mm' => 'nullable|numeric|min:5|max:2000',
            'placements.*.height_mm' => 'nullable|numeric|min:5|max:2000',
            'placements.*.offset_x_mm' => 'nullable|numeric|min:-1000|max:1000',
            'placements.*.offset_y_mm' => 'nullable|numeric|min:-1000|max:1000',
            'placements.*.media_id' => 'nullable|ulid',
            'placements.*.notes' => 'nullable|string|max:1000',
            'placements.*.sort_order' => 'nullable|integer|min:0|max:65535',
        ];

        foreach (self::TECHNICAL_SPEC_KEYS as $key) {
            $rules['technical_specs.'.$key] = 'nullable|string|max:1000';
        }

        return $rules;
    }

    /**
     * Validate each placement against the silhouette template's allowed zones,
     * garment views, physical dimension limits, and safe coordinate boundaries.
     */
    protected function validateStudioPlacements($validator, ?WorkwearModel $model): void
    {
        if (! $this->has('placements') || ! is_array($this->input('placements'))) {
            return;
        }

        $silhouette = $this->input('silhouette')
            ?? $model?->silhouette
            ?? ($this->input('garment_types.0') ?? $model?->garment_types[0] ?? WorkwearSilhouette::COVERALL->value);

        $template = WorkwearStudioTemplateCatalog::getTemplate((string) $silhouette);
        $zones = $template['zones'];
        $unitsPerMm = (float) $template['scale']['units_per_mm'];

        foreach ($this->input('placements', []) as $idx => $placement) {
            if (! is_array($placement)) {
                continue;
            }

            $zoneKey = (string) ($placement['zone'] ?? '');
            if ($zoneKey === '' || ! isset($zones[$zoneKey])) {
                $validator->errors()->add(
                    "placements.{$idx}.zone",
                    "The placement zone '{$zoneKey}' is not available for the '{$template['silhouette']}' template."
                );

                continue;
            }

            $zoneDef = $zones[$zoneKey];
            $view = (string) ($placement['view'] ?? '');
            if (! in_array($view, $zoneDef['views'], true)) {
                $validator->errors()->add(
                    "placements.{$idx}.view",
                    "The '{$view}' view is not supported for zone '{$zoneKey}' on the '{$template['silhouette']}' template."
                );
            }

            $defaults = WorkwearStudioTemplateCatalog::defaultDimensionsForType($placement['type'] ?? 'logo');
            $maxSafeWidthMm = round($zoneDef['safe_bounds']['width'] / $unitsPerMm, 2);
            $maxAllowedWidthMm = min((float) $zoneDef['max_width_mm'], $maxSafeWidthMm);
            $maxSafeHeightMm = round($zoneDef['safe_bounds']['height'] / $unitsPerMm, 2);
            $maxAllowedHeightMm = min((float) $zoneDef['max_height_mm'], $maxSafeHeightMm);

            $hasWidth = isset($placement['width_mm']) && $placement['width_mm'] !== null && $placement['width_mm'] !== '';
            $widthMm = $hasWidth
                ? (float) $placement['width_mm']
                : min($defaults['width_mm'], $maxAllowedWidthMm);

            if ($hasWidth && ($widthMm < (float) $zoneDef['min_width_mm'] || $widthMm > $maxAllowedWidthMm)) {
                $validator->errors()->add(
                    "placements.{$idx}.width_mm",
                    "Width for zone '{$zoneKey}' must be between {$zoneDef['min_width_mm']} mm and {$maxAllowedWidthMm} mm."
                );
            }

            $hasHeight = isset($placement['height_mm']) && $placement['height_mm'] !== null && $placement['height_mm'] !== '';
            $heightMm = $hasHeight
                ? (float) $placement['height_mm']
                : min($defaults['height_mm'], $maxAllowedHeightMm);

            if ($hasHeight && ($heightMm < (float) $zoneDef['min_height_mm'] || $heightMm > $maxAllowedHeightMm)) {
                $validator->errors()->add(
                    "placements.{$idx}.height_mm",
                    "Height for zone '{$zoneKey}' must be between {$zoneDef['min_height_mm']} mm and {$maxAllowedHeightMm} mm."
                );
            }

            $offsetX = isset($placement['offset_x_mm']) && $placement['offset_x_mm'] !== ''
                ? (float) $placement['offset_x_mm']
                : 0.0;
            $offsetY = isset($placement['offset_y_mm']) && $placement['offset_y_mm'] !== ''
                ? (float) $placement['offset_y_mm']
                : 0.0;

            $effectiveWidth = max((float) $zoneDef['min_width_mm'], min($maxAllowedWidthMm, $widthMm));
            $effectiveHeight = max((float) $zoneDef['min_height_mm'], min($maxAllowedHeightMm, $heightMm));

            $geometry = WorkwearStudioTemplateCatalog::computePlacementGeometry(
                $template['silhouette'],
                array_merge($placement, [
                    'width_mm' => $effectiveWidth,
                    'height_mm' => $effectiveHeight,
                    'offset_x_mm' => $offsetX,
                    'offset_y_mm' => $offsetY,
                ])
            );

            if (abs($offsetX) > (float) $zoneDef['max_offset_x_mm'] || ! $geometry['within_safe_x']) {
                $validator->errors()->add(
                    "placements.{$idx}.offset_x_mm",
                    "Horizontal offset ({$offsetX} mm) places '{$zoneKey}' outside the template's safe boundary."
                );
            }

            if (abs($offsetY) > (float) $zoneDef['max_offset_y_mm'] || ! $geometry['within_safe_y']) {
                $validator->errors()->add(
                    "placements.{$idx}.offset_y_mm",
                    "Vertical offset ({$offsetY} mm) places '{$zoneKey}' outside the template's safe boundary."
                );
            }
        }
    }

    /**
     * Placement artwork must come from the model's own media (logos collection).
     */
    protected function validatePlacementMedia($validator, ?WorkwearModel $model): void
    {
        $mediaIds = collect((array) $this->input('placements', []))
            ->pluck('media_id')
            ->filter()
            ->unique()
            ->values();

        if ($mediaIds->isEmpty()) {
            return;
        }

        $owned = $model
            ? $model->media()->whereIn('id', $mediaIds)->where('collection_name', 'logos')->pluck('id')
            : collect();

        foreach ((array) $this->input('placements', []) as $idx => $placement) {
            $mediaId = $placement['media_id'] ?? null;
            if ($mediaId && ! $owned->contains($mediaId)) {
                $validator->errors()->add("placements.{$idx}.media_id", 'The artwork must be a logo uploaded to this model.');
            }
        }
    }

    /**
     * Catalog items attached to a workwear model must be physical products.
     */
    protected function validateCatalogItem($validator): void
    {
        $catalogItemId = $this->input('catalog_item_id');
        if (! $catalogItemId) {
            return;
        }

        $item = CatalogItem::with('product')->find($catalogItemId);
        if (! $item) {
            return;
        }

        $productType = $item->product?->product_type;
        if ($item->item_type !== 'product' || ($productType !== null && $productType !== 'physical')) {
            $validator->errors()->add('catalog_item_id', 'The selected catalog item must be a physical product.');
        }
    }
}
