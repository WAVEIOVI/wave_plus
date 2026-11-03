<?php

declare(strict_types=1);

namespace App\Modules\Catalog\DTOs;

class BlueprintLineResolution
{
    /**
     * @param string $blueprintId
     * @param string $equipmentName
     * @param array $optionValueSnapshot
     * @param array<BlueprintLineData> $lines
     * @param array $missingVariantSelections
     */
    public function __construct(
        public readonly string $blueprintId,
        public readonly string $equipmentName,
        public readonly array $optionValueSnapshot,
        public readonly array $lines,
        public readonly array $missingVariantSelections = [],
    ) {}

    public function hasMissingVariants(): bool
    {
        return !empty($this->missingVariantSelections);
    }

    public function toArray(): array
    {
        return [
            'blueprint_id' => $this->blueprintId,
            'equipment_name' => $this->equipmentName,
            'option_value_snapshot' => $this->optionValueSnapshot,
            'lines' => array_map(fn (BlueprintLineData $line) => $line->toArray(), $this->lines),
            'missing_variant_selections' => $this->missingVariantSelections,
        ];
    }
}
