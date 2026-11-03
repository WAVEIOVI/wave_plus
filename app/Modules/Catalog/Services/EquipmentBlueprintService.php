<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\BlueprintInterventionCycle;
use App\Modules\Catalog\Models\BlueprintOption;
use App\Modules\Catalog\Models\BlueprintOptionValue;
use App\Modules\Catalog\Models\BlueprintServicing;
use App\Modules\Catalog\Models\BlueprintServicingConsumable;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipmentBlueprintService
{
    /**
     * Create an equipment blueprint with structured options, servicing items, and consumables.
     *
     * @param array $data
     * @return EquipmentBlueprint
     */
    public function create(array $data): EquipmentBlueprint
    {
        return DB::transaction(function () use ($data) {
            $this->validateRelationsAndTypes($data);

            $baseData = collect($data)
                ->except(['intervention_cycles', 'servicing_items', 'blueprint_options', 'guidelines'])
                ->toArray();

            $blueprint = EquipmentBlueprint::create($baseData);

            if (! empty($data['guidelines'])) {
                $blueprint->guidelines()->sync($data['guidelines']);
            }

            if (isset($data['intervention_cycles'])) {
                $this->syncInterventionCycles($blueprint, $data['intervention_cycles']);
            }

            // Sync options first so their IDs are available for calculation sources & conditions
            $optionIdMap = [];
            if (isset($data['blueprint_options'])) {
                $optionIdMap = $this->syncBlueprintOptions($blueprint, $data['blueprint_options']);
            }

            if (isset($data['servicing_items'])) {
                $this->syncServicingItems($blueprint, $data['servicing_items'], $optionIdMap);
            }

            return $blueprint;
        });
    }

    /**
     * Update an equipment blueprint with non-destructive syncing.
     *
     * @param EquipmentBlueprint $blueprint
     * @param array $data
     * @return EquipmentBlueprint
     */
    public function update(EquipmentBlueprint $blueprint, array $data): EquipmentBlueprint
    {
        return DB::transaction(function () use ($blueprint, $data) {
            $this->validateRelationsAndTypes($data, $blueprint);

            $baseData = collect($data)
                ->except(['intervention_cycles', 'servicing_items', 'blueprint_options', 'guidelines'])
                ->toArray();

            $blueprint->update($baseData);

            if (array_key_exists('guidelines', $data)) {
                $blueprint->guidelines()->sync($data['guidelines'] ?? []);
            }

            if (array_key_exists('intervention_cycles', $data)) {
                $this->syncInterventionCycles($blueprint, $data['intervention_cycles'] ?? []);
            }

            $optionIdMap = [];
            if (array_key_exists('blueprint_options', $data)) {
                $optionIdMap = $this->syncBlueprintOptions($blueprint, $data['blueprint_options'] ?? []);
            }

            if (array_key_exists('servicing_items', $data)) {
                $this->syncServicingItems($blueprint, $data['servicing_items'] ?? [], $optionIdMap);
            }

            return $blueprint;
        });
    }

    /**
     * Delete an equipment blueprint.
     *
     * @param EquipmentBlueprint $blueprint
     * @return void
     */
    public function delete(EquipmentBlueprint $blueprint): void
    {
        $blueprint->delete();
    }

    /**
     * Validates item types, duplicate servicing items, and duplicate consumables.
     */
    protected function validateRelationsAndTypes(array $data, ?EquipmentBlueprint $existingBlueprint = null): void
    {
        $servicingItems = $data['servicing_items'] ?? null;
        if (! is_array($servicingItems)) {
            return;
        }

        $servicingItemIds = [];
        $consumableItemIds = [];

        foreach ($servicingItems as $sIndex => $sItem) {
            $sId = $sItem['servicing_item_id'] ?? null;
            if ($sId) {
                if (in_array($sId, $servicingItemIds, true)) {
                    throw ValidationException::withMessages([
                        "servicing_items.{$sIndex}.servicing_item_id" => "Duplicate servicing item '{$sId}' attached to blueprint.",
                    ]);
                }
                $servicingItemIds[] = $sId;
            }

            $consumables = $sItem['consumables'] ?? [];
            $seenConsumablesInRow = [];

            foreach ($consumables as $cIndex => $cItem) {
                $cId = $cItem['consumable_item_id'] ?? null;
                if ($cId) {
                    if (in_array($cId, $seenConsumablesInRow, true)) {
                        throw ValidationException::withMessages([
                            "servicing_items.{$sIndex}.consumables.{$cIndex}.consumable_item_id" => "Duplicate consumable '{$cId}' attached to the same servicing item.",
                        ]);
                    }
                    $seenConsumablesInRow[] = $cId;
                    $consumableItemIds[] = $cId;
                }
            }
        }

        // Validate that all servicing items have item_type === 'eq_servicing'
        if (! empty($servicingItemIds)) {
            $invalidServicings = CatalogItem::query()
                ->whereIn('id', $servicingItemIds)
                ->where('item_type', '!=', 'eq_servicing')
                ->pluck('id')
                ->toArray();

            if (! empty($invalidServicings)) {
                throw ValidationException::withMessages([
                    'servicing_items' => "The following items are not 'eq_servicing' items: " . implode(', ', $invalidServicings),
                ]);
            }
        }

        // Validate that all consumable items have item_type === 'consumable'
        if (! empty($consumableItemIds)) {
            $invalidConsumables = CatalogItem::query()
                ->whereIn('id', $consumableItemIds)
                ->where('item_type', '!=', 'consumable')
                ->pluck('id')
                ->toArray();

            if (! empty($invalidConsumables)) {
                throw ValidationException::withMessages([
                    'consumables' => "The following items are not 'consumable' items: " . implode(', ', $invalidConsumables),
                ]);
            }
        }
    }

    protected function syncInterventionCycles(EquipmentBlueprint $blueprint, array $cycles): void
    {
        $blueprint->interventionCycles()->delete();

        foreach ($cycles as $cycle) {
            $blueprint->interventionCycles()->create([
                'intervention_type' => $cycle['intervention_type'],
                'first_interval_months' => (int) ($cycle['first_interval_months'] ?? (($cycle['interval_years'] ?? 1) * 12)),
                'recurring_interval_months' => isset($cycle['recurring_interval_months']) ? (int) $cycle['recurring_interval_months'] : null,
                'notes' => $cycle['notes'] ?? ($cycle['description'] ?? null),
            ]);
        }
    }

    /**
     * Non-destructive sync of blueprint options and values.
     * Returns a map of [submitted_key/id => persisted_id] for newly created options.
     */
    protected function syncBlueprintOptions(EquipmentBlueprint $blueprint, array $options): array
    {
        $optionIdMap = [];
        $keptOptionIds = [];

        foreach ($options as $i => $optData) {
            $optId = $optData['id'] ?? null;
            $option = null;

            if ($optId) {
                $option = $blueprint->blueprintOptions()->find($optId);
            }

            $optionPayload = [
                'code' => $optData['code'] ?? null,
                'name' => $optData['name'],
                'data_type' => $optData['data_type'] ?? 'select',
                'sort_order' => $optData['sort_order'] ?? $i,
            ];

            if ($option) {
                $option->update($optionPayload);
            } else {
                $option = $blueprint->blueprintOptions()->create($optionPayload);
            }

            if ($optId) {
                $optionIdMap[$optId] = $option->id;
            }
            if (! empty($optData['code'])) {
                $optionIdMap[$optData['code']] = $option->id;
            }

            $keptOptionIds[] = $option->id;
            $this->syncOptionValues($option, $optData['values'] ?? []);
        }

        // Delete removed options (with cascade deleting values)
        $blueprint->blueprintOptions()->whereNotIn('id', $keptOptionIds)->delete();

        return $optionIdMap;
    }

    /**
     * Non-destructive sync of option values.
     */
    protected function syncOptionValues(BlueprintOption $option, array $values): void
    {
        $keptValueIds = [];

        foreach ($values as $j => $valData) {
            $valId = $valData['id'] ?? null;
            $valRecord = null;

            if ($valId) {
                $valRecord = $option->values()->find($valId);
            }

            $valPayload = [
                'code' => $valData['code'] ?? null,
                'value' => $valData['value'],
                'numeric_value' => isset($valData['numeric_value']) && $valData['numeric_value'] !== '' ? $valData['numeric_value'] : null,
                'unit' => $valData['unit'] ?? null,
                'sort_order' => $valData['sort_order'] ?? $j,
            ];

            if ($valRecord) {
                $valRecord->update($valPayload);
            } else {
                $valRecord = $option->values()->create($valPayload);
            }

            $keptValueIds[] = $valRecord->id;
        }

        $option->values()->whereNotIn('id', $keptValueIds)->delete();
    }

    /**
     * Non-destructive sync of servicing items and nested consumables.
     */
    protected function syncServicingItems(EquipmentBlueprint $blueprint, array $servicingItems, array $optionIdMap = []): void
    {
        $keptServicingIds = [];

        // Valid option IDs on this blueprint
        $validOptionIds = $blueprint->blueprintOptions()->pluck('id')->toArray();

        foreach ($servicingItems as $svcData) {
            $svcId = $svcData['id'] ?? null;
            $svc = null;

            if ($svcId) {
                $svc = $blueprint->servicingItems()->find($svcId);
            }

            $svcPayload = [
                'servicing_item_id' => $svcData['servicing_item_id'],
                'applicability_conditions' => $svcData['applicability_conditions'] ?? null,
            ];

            if ($svc) {
                $svc->update($svcPayload);
            } else {
                $svc = $blueprint->servicingItems()->create($svcPayload);
            }

            $keptServicingIds[] = $svc->id;

            // Sync level 2 consumables
            $this->syncServicingConsumables($svc, $svcData['consumables'] ?? [], $validOptionIds, $optionIdMap);
        }

        $blueprint->servicingItems()->whereNotIn('id', $keptServicingIds)->delete();
    }

    /**
     * Non-destructive sync of servicing consumables.
     */
    protected function syncServicingConsumables(
        BlueprintServicing $servicing,
        array $consumables,
        array $validOptionIds,
        array $optionIdMap = []
    ): void {
        $keptConsumableIds = [];

        foreach ($consumables as $cData) {
            $cId = $cData['id'] ?? null;
            $cRecord = null;

            if ($cId) {
                $cRecord = $servicing->consumables()->find($cId);
            }

            $sourceOptionId = $cData['calculation_source_option_id'] ?? null;
            if ($sourceOptionId && isset($optionIdMap[$sourceOptionId])) {
                $sourceOptionId = $optionIdMap[$sourceOptionId];
            }

            // If not found directly, check if it matches an option code or fallback
            if ($sourceOptionId && ! in_array($sourceOptionId, $validOptionIds, true)) {
                $blueprint = $servicing->blueprint;
                $matchedByCode = $blueprint?->blueprintOptions()->where('code', $sourceOptionId)->first();
                if ($matchedByCode) {
                    $sourceOptionId = $matchedByCode->id;
                } elseif (($cData['calculation_type'] ?? 'fixed') === 'blueprint_option') {
                    $sourceOptionId = $blueprint?->blueprintOptions()->first()?->id;
                } else {
                    $sourceOptionId = null;
                }
            }

            $calcType = $cData['calculation_type'] ?? 'fixed';
            $calcMethod = $cData['calculation_method'] ?? ($calcType === 'fixed' ? 'fixed' : 'multiply');
            $fixedQty = isset($cData['fixed_quantity']) && $cData['fixed_quantity'] !== '' ? $cData['fixed_quantity'] : null;
            $calcParam = isset($cData['calculation_parameter']) && $cData['calculation_parameter'] !== '' ? $cData['calculation_parameter'] : null;

            $payload = [
                'consumable_item_id' => $cData['consumable_item_id'],
                'calculation_type' => $calcType,
                'calculation_source_option_id' => $sourceOptionId,
                'calculation_method' => $calcMethod,
                'calculation_parameter' => $calcParam,
                'fixed_quantity' => $fixedQty,
                'applicability_conditions' => $cData['applicability_conditions'] ?? null,
            ];

            if ($cRecord) {
                $cRecord->update($payload);
            } else {
                $cRecord = $servicing->consumables()->create($payload);
            }

            $keptConsumableIds[] = $cRecord->id;
        }

        $servicing->consumables()->whereNotIn('id', $keptConsumableIds)->delete();
    }
}
