<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Enums\RfqStatus;
use App\Modules\Crm\Models\PriceRequest;
use App\Modules\Catalog\Services\BlueprintLineBuilderService;
use Illuminate\Support\Facades\DB;

class PriceRequestWriteService
{
    public function __construct(
        private readonly CrmStateMachine $stateMachine,
        private readonly BlueprintLineBuilderService $blueprintLineBuilderService,
    ) {}

    public function create(array $data): PriceRequest
    {
        return DB::transaction(function () use ($data) {
            $payload = collect($data)->except('lines')->toArray();
            $payload['status'] = RfqStatus::New->value;

            $pr = PriceRequest::create($payload);

            foreach ($data['lines'] ?? [] as $line) {
                $pr->lines()->create($line);
            }

            return $pr;
        });
    }

    public function update(PriceRequest $priceRequest, array $data): PriceRequest
    {
        $this->stateMachine->assertEditable('price_request', $priceRequest->status instanceof RfqStatus ? $priceRequest->status->value : (string) $priceRequest->status);

        return DB::transaction(function () use ($priceRequest, $data) {
            $priceRequest->update(collect($data)->except(['lines', 'status'])->toArray());

            if (isset($data['lines'])) {
                $keepIds = collect($data['lines'])->pluck('id')->filter()->toArray();
                $priceRequest->lines()->whereNotIn('id', $keepIds)->delete();

                foreach ($data['lines'] as $line) {
                    if (isset($line['id'])) {
                        $prLine = $priceRequest->lines()->find($line['id']);
                        if ($prLine) {
                            $prLine->update($line);
                        }
                    } else {
                        $priceRequest->lines()->create($line);
                    }
                }
            }

            return $priceRequest;
        });
    }

    public function transition(PriceRequest $priceRequest, string $to): PriceRequest
    {
        $from = $priceRequest->status instanceof RfqStatus ? $priceRequest->status->value : (string) $priceRequest->status;
        $this->stateMachine->assertUserTransition('price_request', $from, $to);
        $priceRequest->update(['status' => $to]);

        return $priceRequest;
    }

    public function delete(PriceRequest $priceRequest): void
    {
        $status = $priceRequest->status instanceof RfqStatus ? $priceRequest->status->value : (string) $priceRequest->status;
        $this->stateMachine->assertDeletable('price_request', $status);
        $priceRequest->delete();
    }

    public function addLinesFromBlueprint(PriceRequest $priceRequest, array $data): PriceRequest
    {
        $this->stateMachine->assertEditable('price_request', $priceRequest->status instanceof RfqStatus ? $priceRequest->status->value : (string) $priceRequest->status);

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

        return DB::transaction(function () use ($priceRequest, $resolution) {
            foreach ($resolution->lines as $lineData) {
                $priceRequest->lines()->create([
                    'catalog_item_id' => $lineData->catalogItemId,
                    'variant_id' => $lineData->variantId,
                    'qty_requested' => $lineData->qty,
                    'suggested_price' => $lineData->unitPrice ?? 0.0,
                    'proposed_price' => $lineData->unitPrice ?? 0.0,
                    'target_margin_pct' => 0.0,
                    'internal_cost' => 0.0,
                    'discount_pct' => 0.0,
                    'actual_margin_pct' => 0.0,
                    'tva_rate_pct' => $lineData->tvaRatePct ?? 19.0,
                    'source_type' => 'blueprint',
                    'source_blueprint_id' => $resolution->blueprintId,
                    'source_blueprint_option_values' => $resolution->optionValueSnapshot,
                    'source_servicing_id' => $lineData->sourceServicingId,
                    'source_consumable_id' => $lineData->sourceConsumableId,
                    'calculation_snapshot' => $lineData->calculationSnapshot,
                ]);
            }

            return $priceRequest->fresh(['lines.catalogItem', 'lines.variant']);
        });
    }
}
