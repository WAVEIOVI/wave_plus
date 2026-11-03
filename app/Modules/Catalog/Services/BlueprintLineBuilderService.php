<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\DTOs\BlueprintLineData;
use App\Modules\Catalog\DTOs\BlueprintLineResolution;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use Illuminate\Validation\ValidationException;

class BlueprintLineBuilderService
{
    public function __construct(
        private BlueprintCalculationService $calculationService,
    ) {}

    /**
     * Resolve equipment blueprint, option values, and servicing into structured document lines.
     *
     * @param EquipmentBlueprint $blueprint
     * @param array<string> $selectedOptionValueIds
     * @param array<string>|null $selectedServicingIds If null, all applicable servicings are included
     * @param array<string, string> $variantSelections Keyed by catalog_item_id, servicing_id, or consumable_id
     * @param float $equipmentQty Multiplier for equipment count
     * @param string $locale
     * @return BlueprintLineResolution
     * @throws ValidationException
     */
    public function resolve(
        EquipmentBlueprint $blueprint,
        array $selectedOptionValueIds,
        ?array $selectedServicingIds = null,
        array $variantSelections = [],
        float $equipmentQty = 1.0,
        string $locale = 'en'
    ): BlueprintLineResolution {
        $blueprint->loadMissing([
            'equipmentClass',
            'blueprintOptions.values',
            'servicingItems.servicingItem.variants',
            'servicingItems.consumables.consumableItem.variants',
            'servicingItems.consumables.calculationSourceOption',
        ]);

        // Get the IDs of all options that belong to this blueprint for validation
        $blueprintOptionIds = $blueprint->blueprintOptions->pluck('id')->map(fn($id) => (string) $id)->toArray();

        $selectedByOptionId = $this->calculationService->resolveSelectedOptions($selectedOptionValueIds, $blueprintOptionIds);
        $equipmentName = $this->calculationService->generateEquipmentName($blueprint, $selectedOptionValueIds, $locale);

        $optionValueSnapshot = [];
        foreach ($selectedByOptionId as $optionId => $val) {
            $optionValueSnapshot[$optionId] = [
                'option_id' => $optionId,
                'option_code' => $val->option?->code,
                'option_name' => $val->option?->getTranslation('name', $locale, false) ?? $val->option?->name,
                'value_id' => $val->id,
                'value_code' => $val->code,
                'value_label' => $val->getTranslation('value', $locale, false) ?? $val->value,
                'numeric_value' => $val->numeric_value !== null ? (float) $val->numeric_value : null,
                'unit' => $val->unit,
            ];
        }

        $lines = [];
        $missingVariants = [];

        foreach ($blueprint->servicingItems as $servicing) {
            // Check servicing applicability conditions
            $isServicingApplicable = $this->calculationService->evaluateConditions(
                $servicing->applicability_conditions,
                $selectedByOptionId,
                $blueprintOptionIds
            );

            if (! $isServicingApplicable) {
                continue;
            }

            // Check if this servicing is selected by the user
            if ($selectedServicingIds !== null && ! in_array($servicing->id, $selectedServicingIds, true)) {
                continue;
            }

            $servicingItem = $servicing->servicingItem;
            if ($servicingItem) {
                $servicingVariants = $servicingItem->variants ?? collect();
                $variantId = null;

                if ($servicingVariants->count() === 1) {
                    $variantId = $servicingVariants->first()->id;
                } elseif ($servicingVariants->count() > 1) {
                    $chosenVariantId = $variantSelections[$servicingItem->id]
                        ?? $variantSelections[$servicing->id]
                        ?? null;

                    if ($chosenVariantId) {
                        if (! $servicingVariants->contains('id', $chosenVariantId)) {
                            throw ValidationException::withMessages([
                                'variant_selections' => "Selected variant {$chosenVariantId} is not valid for servicing item {$servicingItem->name}.",
                            ]);
                        }
                        $variantId = $chosenVariantId;
                    } else {
                        $missingVariants[] = [
                            'context' => 'servicing',
                            'source_id' => $servicing->id,
                            'catalog_item_id' => $servicingItem->id,
                            'item_name' => $servicingItem->name,
                            'variants' => $servicingVariants->map(fn($v) => [
                                'id' => $v->id,
                                'sku' => $v->sku,
                                'name' => $v->name,
                                'price' => $v->price,
                            ])->values()->all(),
                        ];
                    }
                }

                $chosenVariant = $variantId ? $servicingVariants->firstWhere('id', $variantId) : null;
                $unitPrice = $chosenVariant?->price !== null
                    ? (float) $chosenVariant->price
                    : ($servicingItem->base_price !== null ? (float) $servicingItem->base_price : null);

                $lines[] = new BlueprintLineData(
                    catalogItemId: $servicingItem->id,
                    variantId: $variantId,
                    qty: round(1.0 * $equipmentQty, 3),
                    unitId: $servicingItem->uom_id ?? null,
                    sourceServicingId: $servicing->id,
                    sourceConsumableId: null,
                    calculationSnapshot: [
                        'type' => 'servicing',
                        'equipment_qty' => $equipmentQty,
                        'applicability_conditions' => $servicing->applicability_conditions,
                    ],
                    itemTypeSnapshot: 'service',
                    name: $servicingItem->name,
                    sku: $chosenVariant?->sku ?? $servicingItem->code,
                    unitPrice: $unitPrice,
                    catalogItemCode: $servicingItem->code,
                    variantSku: $chosenVariant?->sku,
                    variantCostPrice: $chosenVariant?->cost_price !== null
                        ? (float) $chosenVariant->cost_price
                        : (isset($servicingItem->cost_price) ? (float) $servicingItem->cost_price : null),
                    variantMarkup: $chosenVariant?->markup !== null
                        ? (float) $chosenVariant->markup
                        : (isset($servicingItem->markup) ? (float) $servicingItem->markup : null),
                );
            }

            // Consumables for this servicing
            foreach ($servicing->consumables as $consumable) {
                $isConsumableApplicable = $this->calculationService->evaluateConditions(
                    $consumable->applicability_conditions,
                    $selectedByOptionId,
                    $blueprintOptionIds
                );

                if (! $isConsumableApplicable) {
                    continue;
                }

                $consumableItem = $consumable->consumableItem;
                if (! $consumableItem) {
                    continue;
                }

                $unitQty = $this->calculationService->calculateConsumableQuantity($consumable, $selectedByOptionId, $blueprintOptionIds);
                $totalQty = round($unitQty * $equipmentQty, 3);

                $consumableVariants = $consumableItem->variants ?? collect();
                $variantId = null;

                if ($consumableVariants->count() === 1) {
                    $variantId = $consumableVariants->first()->id;
                } elseif ($consumableVariants->count() > 1) {
                    $chosenVariantId = $variantSelections[$consumable->id]
                        ?? $variantSelections[$consumableItem->id]
                        ?? null;

                    if ($chosenVariantId) {
                        if (! $consumableVariants->contains('id', $chosenVariantId)) {
                            throw ValidationException::withMessages([
                                'variant_selections' => "Selected variant {$chosenVariantId} is not valid for consumable {$consumableItem->name}.",
                            ]);
                        }
                        $variantId = $chosenVariantId;
                    } else {
                        $missingVariants[] = [
                            'context' => 'consumable',
                            'source_id' => $consumable->id,
                            'catalog_item_id' => $consumableItem->id,
                            'item_name' => $consumableItem->name,
                            'variants' => $consumableVariants->map(fn($v) => [
                                'id' => $v->id,
                                'sku' => $v->sku,
                                'name' => $v->name,
                                'price' => $v->price,
                            ])->values()->all(),
                        ];
                    }
                }

                $chosenVariant = $variantId ? $consumableVariants->firstWhere('id', $variantId) : null;
                $unitPrice = $chosenVariant?->price !== null
                    ? (float) $chosenVariant->price
                    : ($consumableItem->base_price !== null ? (float) $consumableItem->base_price : null);

                $lines[] = new BlueprintLineData(
                    catalogItemId: $consumableItem->id,
                    variantId: $variantId,
                    qty: $totalQty,
                    unitId: $consumableItem->uom_id ?? null,
                    sourceServicingId: $servicing->id,
                    sourceConsumableId: $consumable->id,
                    calculationSnapshot: [
                        'type' => 'consumable',
                        'calculation_type' => $consumable->calculation_type,
                        'calculation_method' => $consumable->calculation_method,
                        'calculation_parameter' => $consumable->calculation_parameter,
                        'fixed_quantity' => $consumable->fixed_quantity,
                        'unit_calculated_qty' => $unitQty,
                        'equipment_qty' => $equipmentQty,
                        'total_qty' => $totalQty,
                    ],
                    itemTypeSnapshot: 'spare_part',
                    name: $consumableItem->name,
                    sku: $chosenVariant?->sku ?? $consumableItem->code,
                    unitPrice: $unitPrice,
                    catalogItemCode: $consumableItem->code,
                    variantSku: $chosenVariant?->sku,
                    variantCostPrice: $chosenVariant?->cost_price !== null
                        ? (float) $chosenVariant->cost_price
                        : (isset($consumableItem->cost_price) ? (float) $consumableItem->cost_price : null),
                    variantMarkup: $chosenVariant?->markup !== null
                        ? (float) $chosenVariant->markup
                        : (isset($consumableItem->markup) ? (float) $consumableItem->markup : null),
                );
            }
        }

        return new BlueprintLineResolution(
            blueprintId: $blueprint->id,
            equipmentName: $equipmentName,
            optionValueSnapshot: $optionValueSnapshot,
            lines: $lines,
            missingVariantSelections: $missingVariants,
        );
    }
}
