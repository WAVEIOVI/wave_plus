<?php

declare(strict_types=1);

namespace App\Modules\Catalog\DTOs;

class BlueprintLineData
{
    public function __construct(
        public readonly string $catalogItemId,
        public readonly ?string $variantId,
        public readonly float $qty,
        public readonly ?string $unitId = null,
        public readonly ?string $sourceServicingId = null,
        public readonly ?string $sourceConsumableId = null,
        public readonly array $calculationSnapshot = [],
        public readonly ?string $itemTypeSnapshot = null,
        public readonly ?string $name = null,
        public readonly ?string $sku = null,
        public readonly ?float $unitPrice = null,
        public readonly ?float $tvaRatePct = null,
        public readonly ?string $catalogItemCode = null,
        public readonly ?string $variantSku = null,
        public readonly ?float $variantCostPrice = null,
        public readonly ?float $variantMarkup = null,
    ) {}

    public function toArray(): array
    {
        return [
            'catalog_item_id' => $this->catalogItemId,
            'variant_id' => $this->variantId,
            'qty' => $this->qty,
            'unit_id' => $this->unitId,
            'source_servicing_id' => $this->sourceServicingId,
            'source_consumable_id' => $this->sourceConsumableId,
            'calculation_snapshot' => $this->calculationSnapshot,
            'item_type_snapshot' => $this->itemTypeSnapshot,
            'name' => $this->name,
            'sku' => $this->sku,
            'unit_price' => $this->unitPrice,
            'tva_rate_pct' => $this->tvaRatePct,
            'catalog_item_code' => $this->catalogItemCode,
            'variant_sku' => $this->variantSku,
            'variant_cost_price' => $this->variantCostPrice,
            'variant_markup' => $this->variantMarkup,
        ];
    }
}
