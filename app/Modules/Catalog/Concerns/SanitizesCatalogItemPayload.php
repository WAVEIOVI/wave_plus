<?php

namespace App\Modules\Catalog\Concerns;

trait SanitizesCatalogItemPayload
{
    protected function prepareForValidation(): void
    {
        $itemType = $this->resolveItemType();

        if ($itemType === 'product' && is_array($this->input('product'))) {
            $this->merge([
                'product' => $this->sanitizeProductPayload($this->input('product')),
            ]);
        }
    }

    protected function resolveItemType(): ?string
    {
        if ($this->filled('item_type')) {
            return $this->input('item_type');
        }

        $routeItem = $this->route('catalog_item') ?? $this->route('catalogItem');

        if ($routeItem instanceof \App\Modules\Catalog\Models\CatalogItem) {
            return $routeItem->item_type;
        }

        if (is_string($routeItem)) {
            return \App\Modules\Catalog\Models\CatalogItem::query()
                ->whereKey($routeItem)
                ->value('item_type');
        }

        return null;
    }

    protected function sanitizeProductPayload(array $product): array
    {
        $productType = $product['product_type'] ?? null;

        $allowed = [
            'product_type',
            'technical_specs',
        ];

        $typeSpecificHidden = match ($productType) {
            'physical' => [
                'capacity_value',
                'capacity_unit',
                'material',
                'expiry_years',
                'physical_type',
                'digital_type',
                'service_type',
                'delivery_method',
                'is_downloadable',
                'billable_unit',
                'duration_minutes',
                'max_participants',
            ],
            'digital' => [
                'capacity_value',
                'capacity_unit',
                'material',
                'expiry_years',
                'physical_type',
                'digital_type',
                'service_type',
                'delivery_method',
                'is_downloadable',
                'billable_unit',
                'duration_minutes',
                'max_participants',
            ],
            'service' => [
                'capacity_value',
                'capacity_unit',
                'material',
                'expiry_years',
                'physical_type',
                'digital_type',
                'service_type',
                'billable_unit',
                'duration_minutes',
                'max_participants',
                'delivery_method',
                'is_downloadable',
            ],
            default => [
                'capacity_value',
                'capacity_unit',
                'material',
                'expiry_years',
                'physical_type',
                'digital_type',
                'service_type',
                'delivery_method',
                'is_downloadable',
                'billable_unit',
                'duration_minutes',
                'max_participants',
            ],
        };

        $sanitized = array_intersect_key($product, array_flip($allowed));

        foreach ($typeSpecificHidden as $field) {
            unset($sanitized[$field]);
        }

        return $sanitized;
    }
}
