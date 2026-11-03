<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use App\Modules\Catalog\Services\CatalogLineSnapshotService;
use App\Modules\Crm\Enums\QuotationStatus;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Catalog\Services\BlueprintLineBuilderService;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function __construct(
        private readonly CatalogLineSnapshotService $lineSnapshotService,
        private readonly CrmStateMachine $stateMachine,
        private readonly BlueprintLineBuilderService $blueprintLineBuilderService,
    ) {}

    public function createQuotation(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $payload = collect($data)->except('lines')->toArray();
            $payload['status'] = QuotationStatus::Draft->value;
            $quotation = Quotation::create($payload);

            $totalHt = $totalTva = $totalTtc = 0;

            if (isset($data['lines'])) {
                foreach ($data['lines'] as $line) {
                    $line = array_merge($line, $this->lineSnapshotService->forLine($line));
                    $quotation->lines()->create($line);
                    $totalHt += $line['total_ht'] ?? 0;
                    $totalTva += $line['total_tva'] ?? 0;
                    $totalTtc += $line['total_ttc'] ?? 0;
                }
            }

            $quotation->update([
                'total_ht' => $totalHt,
                'total_tva' => $totalTva,
                'total_ttc' => $totalTtc,
            ]);

            return $quotation;
        });
    }

    public function updateQuotation(Quotation $quotation, array $data): Quotation
    {
        $this->stateMachine->assertEditable('quotation', $this->statusValue($quotation));

        return DB::transaction(function () use ($quotation, $data) {
            $quotation->update(collect($data)->except(['lines', 'status'])->toArray());

            if (isset($data['lines'])) {
                $keepIds = collect($data['lines'])->pluck('id')->filter()->toArray();
                $quotation->lines()->whereNotIn('id', $keepIds)->delete();

                $totalHt = $totalTva = $totalTtc = 0;

                foreach ($data['lines'] as $line) {
                    $line = array_merge($line, $this->lineSnapshotService->forLine($line));
                    if (isset($line['id'])) {
                        $qLine = $quotation->lines()->find($line['id']);
                        if ($qLine) {
                            $qLine->update($line);
                        }
                    } else {
                        $quotation->lines()->create($line);
                    }
                    $totalHt += $line['total_ht'] ?? 0;
                    $totalTva += $line['total_tva'] ?? 0;
                    $totalTtc += $line['total_ttc'] ?? 0;
                }

                $quotation->update([
                    'total_ht' => $totalHt,
                    'total_tva' => $totalTva,
                    'total_ttc' => $totalTtc,
                ]);
            }

            return $quotation;
        });
    }

    public function transition(Quotation $quotation, string $to, array $context = []): Quotation
    {
        $from = $this->statusValue($quotation);
        $this->stateMachine->assertUserTransition('quotation', $from, $to, [
            'approval_required' => (bool) ($context['approval_required'] ?? $quotation->approval_required),
        ]);

        $updates = ['status' => $to];
        if ($to === QuotationStatus::Sent->value) {
            $updates['sent_at'] = $quotation->sent_at ?? now();
        }
        if ($to === QuotationStatus::Accepted->value) {
            $updates['accepted_at'] = $quotation->accepted_at ?? now();
            if (! empty($context['approved_by'])) {
                $updates['approved_by'] = $context['approved_by'];
                $updates['approved_at'] = now();
            }
        }

        $quotation->update($updates);

        return $quotation;
    }

    public function delete(Quotation $quotation): void
    {
        $this->stateMachine->assertDeletable('quotation', $this->statusValue($quotation));
        $quotation->delete();
    }

    public function addLinesFromBlueprint(Quotation $quotation, array $data): Quotation
    {
        $this->stateMachine->assertEditable('quotation', $this->statusValue($quotation));

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

        return DB::transaction(function () use ($quotation, $resolution) {
            $totalHtDelta = 0.0;
            $totalTvaDelta = 0.0;
            $totalTtcDelta = 0.0;

            foreach ($resolution->lines as $lineData) {
                $linePrice = (float) ($lineData->unitPrice ?? 0.0);
                $qty = $lineData->qty;
                $lineHt = $qty * $linePrice;
                $tvaRate = (float) ($lineData->tvaRatePct ?? 19.0);
                $lineTva = $lineHt * ($tvaRate / 100);
                $lineTtc = $lineHt + $lineTva;

                $lineRecordData = [
                    'catalog_item_id' => $lineData->catalogItemId,
                    'variant_id' => $lineData->variantId,
                    'qty' => $qty,
                    'unit_price' => $linePrice,
                    'discount_pct' => 0.0,
                    'unit_price_after_discount' => $linePrice,
                    'tva_rate_pct' => $tvaRate,
                    'total_ht' => $lineHt,
                    'total_tva' => $lineTva,
                    'total_ttc' => $lineTtc,
                    'item_type_snapshot' => $lineData->itemTypeSnapshot,
                    'source_type' => 'blueprint',
                    'source_blueprint_id' => $resolution->blueprintId,
                    'source_blueprint_option_values' => $resolution->optionValueSnapshot,
                    'source_servicing_id' => $lineData->sourceServicingId,
                    'source_consumable_id' => $lineData->sourceConsumableId,
                    'calculation_snapshot' => $lineData->calculationSnapshot,
                ];

                $lineRecordData = array_merge($lineRecordData, $this->lineSnapshotService->forLine($lineRecordData));
                $quotation->lines()->create($lineRecordData);

                $totalHtDelta += $lineHt;
                $totalTvaDelta += $lineTva;
                $totalTtcDelta += $lineTtc;
            }

            $quotation->update([
                'total_ht' => round((float) $quotation->total_ht + $totalHtDelta, 3),
                'total_tva' => round((float) $quotation->total_tva + $totalTvaDelta, 3),
                'total_ttc' => round((float) $quotation->total_ttc + $totalTtcDelta, 3),
            ]);

            return $quotation->fresh(['lines.catalogItem', 'lines.variant']);
        });
    }

    private function statusValue(Quotation $quotation): string
    {
        return $quotation->status instanceof QuotationStatus ? $quotation->status->value : (string) $quotation->status;
    }
}
