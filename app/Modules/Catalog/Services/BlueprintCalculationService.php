<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\BlueprintOptionValue;
use App\Modules\Catalog\Models\BlueprintServicing;
use App\Modules\Catalog\Models\BlueprintServicingConsumable;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use Illuminate\Support\Collection;

class BlueprintCalculationService
{
    /**
     * Resolves an array of selected option value IDs into a Collection of BlueprintOptionValue models,
     * keyed by option_id, and validates that each value belongs to the given blueprint.
     *
     * @param array<string> $selectedOptionValueIds
     * @param array<string> $blueprintOptionIds IDs of options that belong to the blueprint
     * @return Collection<string, BlueprintOptionValue>
     * @throws \InvalidArgumentException if a selected option value does not belong to the blueprint
     */
    public function resolveSelectedOptions(array $selectedOptionValueIds, array $blueprintOptionIds): Collection
    {
        if (empty($selectedOptionValueIds)) {
            return collect();
        }

        $values = BlueprintOptionValue::query()
            ->whereIn('id', $selectedOptionValueIds)
            ->with('option')
            ->get()
            ->keyBy('option_id');

        // Validate that each selected option value belongs to the blueprint
        $values->each(function ($value, $optionId) use ($blueprintOptionIds) {
            if (! in_array($optionId, $blueprintOptionIds, true)) {
                throw new \InvalidArgumentException("Selected option value {$value->id} does not belong to the blueprint.");
            }
        });

        return $values;
    }

    /**
     * Evaluates whether a set of applicability conditions matches the selected options.
     *
     * @param array|null $conditions
     * @param Collection<string, BlueprintOptionValue> $selectedByOptionId
     * @param array<string> $blueprintOptionIds IDs of options that belong to the blueprint
     * @return bool
     * @throws \InvalidArgumentException if a condition references an option not belonging to the blueprint
     */
    public function evaluateConditions(?array $conditions, Collection $selectedByOptionId, array $blueprintOptionIds): bool
    {
        if (empty($conditions)) {
            return true;
        }

        // Support current and legacy condition formats, plus a sequential rules array.
        $matchType = $conditions['match_type'] ?? $conditions['match'] ?? 'all';
        if (isset($conditions['rules']) && is_array($conditions['rules'])) {
            $rules = $conditions['rules'];
        } elseif (isset($conditions['conditions']) && is_array($conditions['conditions'])) {
            $rules = $conditions['conditions'];
        } else {
            $rules = array_is_list($conditions) ? $conditions : [$conditions];
        }

        if (empty($rules)) {
            return true;
        }

        // Validate that each rule's option_id belongs to the blueprint
        foreach ($rules as $rule) {
            $optionId = $rule['option_id'] ?? null;
            if ($optionId && ! in_array($optionId, $blueprintOptionIds, true)) {
                throw new \InvalidArgumentException("Condition references option {$optionId} which does not belong to the blueprint.");
            }
        }

        if ($matchType === 'any') {
            foreach ($rules as $rule) {
                if ($this->evaluateSingleRule($rule, $selectedByOptionId)) {
                    return true;
                }
            }
            return false;
        }

        // Default 'all' (AND logic)
        foreach ($rules as $rule) {
            if (! $this->evaluateSingleRule($rule, $selectedByOptionId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluates a single rule without using eval().
     *
     * @param array $rule
     * @param Collection<string, BlueprintOptionValue> $selectedByOptionId
     * @return bool
     */
    public function evaluateSingleRule(array $rule, Collection $selectedByOptionId): bool
    {
        $optionId = $rule['option_id'] ?? null;
        if (! $optionId) {
            return true;
        }

        /** @var BlueprintOptionValue|null $selectedValue */
        $selectedValue = $selectedByOptionId->get($optionId);
        if (! $selectedValue) {
            // Option was not selected
            return false;
        }

        $operator = strtolower((string) ($rule['operator'] ?? 'equals'));
        $targetValue = $rule['value'] ?? null;

        $selectedNumeric = $selectedValue->numeric_value !== null ? (float) $selectedValue->numeric_value : null;
        $targetNumeric = is_numeric($targetValue) ? (float) $targetValue : null;

        return match ($operator) {
            'equals', '==' => $this->checkEquals($selectedValue, $targetValue, $selectedNumeric, $targetNumeric),
            'not_equals', '!=' => ! $this->checkEquals($selectedValue, $targetValue, $selectedNumeric, $targetNumeric),
            'greater_than', '>' => $selectedNumeric !== null && $targetNumeric !== null && $selectedNumeric > $targetNumeric,
            'greater_than_or_equal', '>=' => $selectedNumeric !== null && $targetNumeric !== null && $selectedNumeric >= $targetNumeric,
            'less_than', '<' => $selectedNumeric !== null && $targetNumeric !== null && $selectedNumeric < $targetNumeric,
            'less_than_or_equal', '<=' => $selectedNumeric !== null && $targetNumeric !== null && $selectedNumeric <= $targetNumeric,
            'in' => $this->checkIn($selectedValue, (array) $targetValue),
            'not_in' => ! $this->checkIn($selectedValue, (array) $targetValue),
            default => throw new \InvalidArgumentException("Unknown condition operator: {$operator}"),
        };
    }

    private function checkEquals(object $selectedValue, mixed $targetValue, ?float $selectedNumeric, ?float $targetNumeric): bool
    {
        // 1. Direct ID match
        if ($selectedValue->id === $targetValue) {
            return true;
        }

        // 2. Numeric match if both are numeric
        if ($selectedNumeric !== null && $targetNumeric !== null) {
            return abs($selectedNumeric - $targetNumeric) < 0.00001;
        }

        // 3. Option code or value string match
        if ($selectedValue->code && (string) $selectedValue->code === (string) $targetValue) {
            return true;
        }

        return false;
    }

    private function checkIn(object $selectedValue, array $targetArray): bool
    {
        foreach ($targetArray as $target) {
            $targetNumeric = is_numeric($target) ? (float) $target : null;
            $selectedNumeric = $selectedValue->numeric_value !== null ? (float) $selectedValue->numeric_value : null;
            if ($this->checkEquals($selectedValue, $target, $selectedNumeric, $targetNumeric)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculates the required quantity for a consumable item given the selected options.
     *
     * @param object $consumable  BlueprintServicingConsumable or test stub with the same properties
     * @param Collection<string, BlueprintOptionValue> $selectedByOptionId
     * @param array<string> $blueprintOptionIds IDs of options that belong to the blueprint
     * @return float
     * @throws \InvalidArgumentException if the calculation source option does not belong to the blueprint
     */
    public function calculateConsumableQuantity(
        object     $consumable,
        Collection $selectedByOptionId,
        array      $blueprintOptionIds
    ): float {
        $calcType = $consumable->calculation_type ?: 'fixed';

        if ($calcType === 'fixed') {
            return (float) ($consumable->fixed_quantity ?? 1.0);
        }

        if ($calcType === 'blueprint_option') {
            $sourceOptionId = $consumable->calculation_source_option_id;
            if (! $sourceOptionId) {
                throw new \InvalidArgumentException("Blueprint option-based calculation requires a calculation source option ID.");
            }

            // Validate that the source option belongs to the blueprint
            if (! in_array($sourceOptionId, $blueprintOptionIds, true)) {
                throw new \InvalidArgumentException("Calculation source option {$sourceOptionId} does not belong to the blueprint.");
            }

            /** @var BlueprintOptionValue|null $selectedValue */
            $selectedValue = $selectedByOptionId->get($sourceOptionId);
            $numericBase = $selectedValue && $selectedValue->numeric_value !== null ? (float) $selectedValue->numeric_value : 0.0;
            $param = $consumable->calculation_parameter !== null ? (float) $consumable->calculation_parameter : 1.0;

            return match ($consumable->calculation_method) {
                'multiply' => round($numericBase * $param, 4),
                'add' => round($numericBase + $param, 4),
                'subtract' => round(max(0.0, $numericBase - $param), 4),
                'divide' => $param != 0.0 ? round($numericBase / $param, 4) : 0.0,
                default => throw new \InvalidArgumentException("Unknown calculation method: {$consumable->calculation_method}"),
            };
        }

        throw new \InvalidArgumentException("Unknown calculation type: {$calcType}");
    }

    /**
     * Generates standard equipment name based on blueprint and selected options.
     *
     * @param EquipmentBlueprint $blueprint
     * @param array<string> $selectedOptionValueIds
     * @param string $locale
     * @return string
     */
    public function generateEquipmentName(
        EquipmentBlueprint $blueprint,
        array $selectedOptionValueIds,
        string $locale = 'en'
    ): string {
        $blueprintName = $blueprint->getTranslation('name', $locale, false)
            ?: ($blueprint->getTranslation('name', 'fr', false) ?: $blueprint->code);

        if (empty($selectedOptionValueIds)) {
            return $blueprintName;
        }

        $selectedValues = BlueprintOptionValue::query()
            ->whereIn('id', $selectedOptionValueIds)
            ->with('option')
            ->get()
            ->sortBy(fn($val) => $val->option?->sort_order ?? 0);

        $suffixParts = [];
        foreach ($selectedValues as $val) {
            $label = $val->getTranslation('value', $locale, false)
                ?: ($val->getTranslation('value', 'fr', false) ?: ($val->code ?? (string) $val->numeric_value));

            if ($val->unit && ! str_contains((string) $label, (string) $val->unit)) {
                $suffixParts[] = trim($label . ' ' . $val->unit);
            } else {
                $suffixParts[] = trim((string) $label);
            }
        }

        if (empty($suffixParts)) {
            return $blueprintName;
        }

        return $blueprintName . ' - ' . implode(' / ', $suffixParts);
    }

    /**
     * Full preview evaluation of a blueprint configuration.
     *
     * @param EquipmentBlueprint $blueprint
     * @param array<string> $selectedOptionValueIds
     * @param string $locale
     * @return array
     */
    public function preview(
        EquipmentBlueprint $blueprint,
        array $selectedOptionValueIds,
        string $locale = 'en'
    ): array {
        $blueprint->loadMissing([
            'equipmentClass',
            'blueprintOptions.values',
            'servicingItems.servicingItem',
            'servicingItems.consumables.consumableItem',
            'servicingItems.consumables.calculationSourceOption',
        ]);

        // Get the IDs of all options that belong to this blueprint for validation
        $blueprintOptionIds = $blueprint->blueprintOptions->pluck('id')->map(fn($id) => (string) $id)->toArray();

        $selectedByOptionId = $this->resolveSelectedOptions($selectedOptionValueIds, $blueprintOptionIds);
        $generatedName = $this->generateEquipmentName($blueprint, $selectedOptionValueIds, $locale);

        $selectedDetails = [];
        foreach ($selectedByOptionId as $optionId => $val) {
            $selectedDetails[] = [
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

        $applicableServicing = [];
        $totalConsumablesSummary = [];

        foreach ($blueprint->servicingItems as $servicing) {
            $isServicingApplicable = $this->evaluateConditions($servicing->applicability_conditions, $selectedByOptionId, $blueprintOptionIds);

            $consumablesList = [];
            if ($isServicingApplicable) {
                foreach ($servicing->consumables as $consumable) {
                    $isConsumableApplicable = $this->evaluateConditions($consumable->applicability_conditions, $selectedByOptionId, $blueprintOptionIds);

                    if ($isConsumableApplicable) {
                        $qty = $this->calculateConsumableQuantity($consumable, $selectedByOptionId, $blueprintOptionIds);
                        $consumableItem = $consumable->consumableItem;

                        $consumablesList[] = [
                            'id' => $consumable->id,
                            'consumable_item_id' => $consumable->consumable_item_id,
                            'consumable_name' => $consumableItem?->name ?? 'Unknown Consumable',
                            'consumable_code' => $consumableItem?->code,
                            'calculation_type' => $consumable->calculation_type,
                            'calculation_method' => $consumable->calculation_method,
                            'calculated_quantity' => $qty,
                            'unit' => $consumableItem?->uom?->code ?? 'unit',
                        ];

                        // Aggregate summary
                        $itemId = (string) $consumable->consumable_item_id;
                        if (! isset($totalConsumablesSummary[$itemId])) {
                            $totalConsumablesSummary[$itemId] = [
                                'catalog_item_id' => $itemId,
                                'code' => $consumableItem?->code,
                                'name' => $consumableItem?->name,
                                'total_quantity' => 0.0,
                                'unit' => $consumableItem?->uom?->code ?? 'unit',
                            ];
                        }
                        $totalConsumablesSummary[$itemId]['total_quantity'] = round(
                            $totalConsumablesSummary[$itemId]['total_quantity'] + $qty,
                            4
                        );
                    }
                }
            }

            $applicableServicing[] = [
                'id' => $servicing->id,
                'servicing_item_id' => $servicing->servicing_item_id,
                'servicing_name' => $servicing->servicingItem?->name ?? 'Unknown Servicing',
                'servicing_code' => $servicing->servicingItem?->code,
                'is_applicable' => $isServicingApplicable,
                'conditions' => $servicing->applicability_conditions,
                'consumables' => $consumablesList,
            ];
        }

        return [
            'blueprint_id' => $blueprint->id,
            'blueprint_code' => $blueprint->code,
            'equipment_class' => $blueprint->equipmentClass?->name ?? $blueprint->equipmentClass?->code,
            'generated_name' => $generatedName,
            'selected_options' => $selectedDetails,
            'servicing' => $applicableServicing,
            'consumables_summary' => array_values($totalConsumablesSummary),
        ];
    }
}
