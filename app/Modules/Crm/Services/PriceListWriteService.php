<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use App\Modules\Catalog\Services\BlueprintLineBuilderService;
use App\Modules\Crm\Models\PriceList;
use Illuminate\Support\Facades\DB;

class PriceListWriteService
{
    public function __construct(
        private readonly CrmStateMachine $stateMachine,
        private readonly BlueprintLineBuilderService $blueprintLineBuilderService,
    ) {}

    public function create(array $data): PriceList
    {
        return DB::transaction(function () use ($data) {
            $payload = collect($data)->except('lines')->toArray();
            $payload['is_active'] = false;

            $pl = PriceList::create($payload);

            foreach ($data['lines'] ?? [] as $line) {
                $pl->lines()->create($line);
            }

            return $pl;
        });
    }

    public function update(PriceList $priceList, array $data): PriceList
    {
        $this->stateMachine->assertEditable('price_list', $this->derivedStatus($priceList));

        return DB::transaction(function () use ($priceList, $data) {
            $priceList->update(collect($data)->except(['lines', 'status', 'is_active'])->toArray());

            if (isset($data['lines'])) {
                $keepIds = collect($data['lines'])->pluck('id')->filter()->toArray();
                $priceList->lines()->whereNotIn('id', $keepIds)->delete();

                foreach ($data['lines'] as $line) {
                    if (isset($line['id'])) {
                        $priceList->lines()->where('id', $line['id'])->update($line);
                    } else {
                        $priceList->lines()->create($line);
                    }
                }
            }

            return $priceList;
        });
    }

    public function transition(PriceList $priceList, string $to): PriceList
    {
        $this->stateMachine->assertUserTransition('price_list', $this->derivedStatus($priceList), $to);

        $priceList->update(match ($to) {
            'active' => ['is_active' => true, 'valid_to' => null],
            'expired' => ['is_active' => true, 'valid_to' => today()->subDay()],
            default => ['is_active' => false],
        });

        return $priceList;
    }

    public function delete(PriceList $priceList): void
    {
        $this->stateMachine->assertDeletable('price_list', $this->derivedStatus($priceList));
        $priceList->delete();
    }

    public function derivedStatus(PriceList $priceList): string
    {
        if (! $priceList->is_active) {
            return 'draft';
        }

        if ($priceList->valid_to && $priceList->valid_to->lt(today())) {
            return 'expired';
        }

        return 'active';
    }

    public function addLinesFromBlueprint(PriceList $priceList, array $data): PriceList
    {
        $this->stateMachine->assertEditable('price_list', $this->derivedStatus($priceList));

        $blueprintId = $data['blueprint_id'] ?? null;
        if (! $blueprintId) {
            abort(422, 'A blueprint_id is required.');
        }

        $blueprint = \App\Modules\Catalog\Models\EquipmentBlueprint::findOrFail($blueprintId);
        $resolution = $this->blueprintLineBuilderService->resolve(
            blueprint: $blueprint,
            selectedOptionValueIds: $data['selected_option_values'] ?? [],
            selectedServicingIds: $data['selected_servicings'] ?? null,
            variantSelections: $data['variant_selections'] ?? [],
            equipmentQty: isset($data['equipment_qty']) ? (float) $data['equipment_qty'] : 1.0,
            locale: $data['locale'] ?? app()->getLocale(),
        );

        if ($resolution->hasMissingVariants()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'variant_selections' => 'Some items have multiple variants that must be selected.',
                'missing_variants' => $resolution->missingVariantSelections,
            ]);
        }

        return DB::transaction(function () use ($priceList, $resolution) {
            foreach ($resolution->lines as $lineData) {
                $price = (float) ($lineData->unitPrice ?? 0.0);
                $priceList->lines()->create([
                    'catalog_item_id' => $lineData->catalogItemId,
                    'variant_id' => $lineData->variantId,
                    'price_computation' => 'fixed',
                    'fixed_price' => $price,
                    'computed_price' => $price,
                    'sales_price' => $price,
                    'min_qty' => 1.0,
                    'source_type' => 'blueprint',
                    'source_blueprint_id' => $resolution->blueprintId,
                    'source_blueprint_option_values' => $resolution->optionValueSnapshot,
                    'source_servicing_id' => $lineData->sourceServicingId,
                    'source_consumable_id' => $lineData->sourceConsumableId,
                    'calculation_snapshot' => $lineData->calculationSnapshot,
                ]);
            }

            return $priceList->fresh(['lines.catalogItem', 'lines.variant']);
        });
    }
}
