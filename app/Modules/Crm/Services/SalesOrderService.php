<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Services\CatalogLineSnapshotService;
use App\Modules\Crm\Enums\FeasibilityStatus;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Modules\OperationalHub\Enums\SwoStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(
        private PriceResolutionService $priceResolutionService,
        private FeasibilityService $feasibilityService,
        private SalesOrderAcceptanceService $acceptanceService,
        private CatalogLineSnapshotService $lineSnapshotService,
        private CrmStateMachine $stateMachine,
        private \App\Modules\Catalog\Services\BlueprintLineBuilderService $blueprintLineBuilderService,
    ) {}

    /**
     * Create a new Sales Order with backend type restrictions.
     */
    public function createSalesOrder(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data) {
            $soType = $data['so_type'] instanceof \BackedEnum ? $data['so_type']->value : ($data['so_type'] ?? 'sales');
            $lines = $data['lines'] ?? [];

            // Validate client ownership of quotation/contract
            if (! empty($data['client_id'])) {
                if (! empty($data['quotation_id'])) {
                    $quote = \App\Modules\Crm\Models\Quotation::find($data['quotation_id']);
                    if ($quote && $quote->client_id !== $data['client_id']) {
                        abort(422, 'Quotation does not belong to the selected client.');
                    }
                }

                if (! empty($data['contract_id'])) {
                    $contract = \App\Modules\Crm\Models\Contract::find($data['contract_id']);
                    if ($contract && $contract->client_id !== $data['client_id']) {
                        abort(422, 'Contract does not belong to the selected client.');
                    }
                }
            }

            // Validate requested fulfillment mode
            if (! empty($data['requested_fulfillment_mode'])) {
                $modeVal = $data['requested_fulfillment_mode'] instanceof \BackedEnum
                    ? $data['requested_fulfillment_mode']->value
                    : (string) $data['requested_fulfillment_mode'];
                if (! \App\Modules\Crm\Enums\SoFulfillmentMode::isValidForSoType($modeVal, $soType)) {
                    abort(422, "Invalid fulfillment mode '{$modeVal}' for Sales Order type '{$soType}'.");
                }
            }

            // Backend validation of catalog item types if lines provided
            if (! empty($lines)) {
                $this->validateLineItemTypes($soType, $lines);
            }

            $soData = collect($data)->except(['lines', 'locations'])->toArray();
            $soData['so_type'] = $soType;
            // Status is always forced to Draft on creation — cannot be set by the caller.
            $soData['status'] = SalesOrderStatus::Draft->value;
            $soData['pricing_locked_at'] = $soData['pricing_locked_at'] ?? now();
            $soData['starter_locked_at'] = $soData['starter_locked_at'] ?? now();

            // Canonical creation_path resolution
            $soData['creation_path'] = $soData['creation_path'] ?? \App\Modules\Crm\Enums\SoCreationPath::Manual;

            $so = SalesOrder::create($soData);

            // Handle locations
            if (! empty($data['locations']) && is_array($data['locations'])) {
                foreach ($data['locations'] as $loc) {
                    if (! empty($loc['bp_address_id'])) {
                        $so->locations()->create([
                            'bp_address_id' => $loc['bp_address_id'],
                            'client_site_id' => $loc['client_site_id'] ?? null,
                            'purpose' => $loc['purpose'] ?? 'delivery',
                            'is_primary' => (bool) ($loc['is_primary'] ?? false),
                            'metadata' => $loc['metadata'] ?? null,
                        ]);
                    }
                }
            }

            $totalHt = $totalTva = $totalTtc = 0.0;
            $hasService = false;
            $hasGoods = false;

            foreach ($lines as $line) {
                // Ensure pricing is set
                $linePrice = (float) ($line['unit_price'] ?? 0);
                if ($linePrice <= 0 && ! empty($line['catalog_item_id'])) {
                    $resolved = $this->priceResolutionService->resolvePrice(
                        $line['catalog_item_id'],
                        $line['variant_id'] ?? null,
                        $so->client_id,
                        $so->contract_id,
                        $so->price_list_id
                    );
                    $linePrice = $resolved['price'];
                    $line['unit_price'] = $linePrice;
                }

                $qty = (float) ($line['qty'] ?? 1);
                $discountPct = (float) ($line['discount_pct'] ?? 0);
                $lineHt = $qty * $linePrice * (1 - ($discountPct / 100));
                $tvaRate = (float) ($line['tva_rate'] ?? 19.0);
                $lineTva = $lineHt * ($tvaRate / 100);
                $lineTtc = $lineHt + $lineTva;

                $line['total_ht'] = $lineHt;
                $line['total_tva'] = $lineTva;
                $line['total_ttc'] = $lineTtc;

                $type = $this->normalizeItemTypeSnapshot($soType, $line['item_type_snapshot'] ?? null);
                $line['item_type_snapshot'] = $type;
                $line = array_merge($line, $this->lineSnapshotService->forLine($line));
                if (empty($line['unit_id']) && ! empty($line['catalog_item_id'])) {
                    $line['unit_id'] = CatalogItem::query()->find($line['catalog_item_id'])?->uom_id;
                }

                $so->lines()->create($line);

                $totalHt += $lineHt;
                $totalTva += $lineTva;
                $totalTtc += $lineTtc;

                if ($type === 'service') {
                    $hasService = true;
                } else {
                    $hasGoods = true;
                }
            }

            $so->update([
                'total_ht' => $totalHt,
                'total_tva' => $totalTva,
                'total_ttc' => $totalTtc,
                'has_service_lines' => $soType === 'service',
                'has_goods_lines' => $soType === 'sales',
            ]);

            return $so->load(['client', 'lines.catalogItem', 'lines.variant', 'contract', 'quotation']);
        });
    }

    /**
     * Update an existing draft Sales Order.
     */
    public function updateSalesOrder(SalesOrder $salesOrder, array $data): SalesOrder
    {
        if (! $salesOrder->canBeEdited()) {
            abort(422, "Cannot edit Sales Order {$salesOrder->reference} in status '{$salesOrder->status->value}'. Accepted orders must be amended via versioning.");
        }

        if ($salesOrder->isStarterLocked()) {
            $starterFields = [
                'client_id',
                'so_type',
                'creation_path',
                'quotation_id',
                'contract_id',
                'requested_fulfillment_mode',
                'requested_delivery_date_from',
                'requested_delivery_date_to',
                'committed_delivery_date_from',
                'committed_delivery_date_to',
            ];

            foreach ($starterFields as $field) {
                if (array_key_exists($field, $data)) {
                    $currentVal = $salesOrder->$field;
                    if ($currentVal instanceof \BackedEnum) {
                        $currentVal = $currentVal->value;
                    } elseif ($currentVal instanceof \Carbon\CarbonInterface) {
                        $currentVal = $currentVal->toDateString();
                    }

                    $newVal = $data[$field];
                    if ($newVal instanceof \BackedEnum) {
                        $newVal = $newVal->value;
                    } elseif ($newVal instanceof \Carbon\CarbonInterface) {
                        $newVal = $newVal->toDateString();
                    } elseif (is_string($newVal) && str_contains($field, 'date')) {
                        $newVal = substr($newVal, 0, 10);
                    }

                    if ((string) ($currentVal ?? '') !== (string) ($newVal ?? '')) {
                        abort(422, "Cannot modify locked starter field '{$field}' after initial save. Create an amendment to change order foundation.");
                    }
                }
            }
        }

        return DB::transaction(function () use ($salesOrder, $data) {
            // Prevent changing so_type
            $soType = $salesOrder->so_type->value;

            $updateData = collect($data)->except(['lines', 'so_type'])->toArray();
            $salesOrder->update($updateData);

            if (isset($data['lines'])) {
                $lines = $data['lines'];
                $this->validateLineItemTypes($soType, $lines);

                $keepIds = collect($lines)->pluck('id')->filter()->toArray();
                $salesOrder->lines()->whereNotIn('id', $keepIds)->delete();

                $totalHt = $totalTva = $totalTtc = 0.0;
                $hasService = false;
                $hasGoods = false;

                foreach ($lines as $line) {
                    $qty = (float) ($line['qty'] ?? 1);
                    $linePrice = (float) ($line['unit_price'] ?? 0);
                    $discountPct = (float) ($line['discount_pct'] ?? 0);
                    $lineHt = $qty * $linePrice * (1 - ($discountPct / 100));
                    $tvaRate = (float) ($line['tva_rate'] ?? 19.0);
                    $lineTva = $lineHt * ($tvaRate / 100);
                    $lineTtc = $lineHt + $lineTva;

                    $line['total_ht'] = $lineHt;
                    $line['total_tva'] = $lineTva;
                    $line['total_ttc'] = $lineTtc;

                    $type = $this->normalizeItemTypeSnapshot($soType, $line['item_type_snapshot'] ?? null);
                    $line['item_type_snapshot'] = $type;
                    $line = array_merge($line, $this->lineSnapshotService->forLine($line));

                    if (isset($line['id'])) {
                        $salesOrder->lines()->where('id', $line['id'])->update($line);
                    } else {
                        $salesOrder->lines()->create($line);
                    }

                    $totalHt += $lineHt;
                    $totalTva += $lineTva;
                    $totalTtc += $lineTtc;

                    if ($type === 'service') {
                        $hasService = true;
                    } else {
                        $hasGoods = true;
                    }
                }

                $salesOrder->update([
                    'total_ht' => $totalHt,
                    'total_tva' => $totalTva,
                    'total_ttc' => $totalTtc,
                    'has_service_lines' => $soType === 'service',
                    'has_goods_lines' => $soType === 'sales',
                ]);
            }

            return $salesOrder->fresh(['client', 'lines.catalogItem', 'lines.variant', 'contract', 'quotation']);
        });
    }

    /**
     * Submit draft SO for Feasibility Review.
     * Transitions: draft → submitted (awaiting reviewer pickup)
     */
    public function submitForReview(SalesOrder $salesOrder): SalesOrder
    {
        if ($salesOrder->status !== SalesOrderStatus::Draft) {
            abort(422, 'Only draft Sales Orders can be submitted for review.');
        }

        $salesOrder->update([
            'status' => SalesOrderStatus::Submitted,
            'feasibility_status' => FeasibilityStatus::Pending,
        ]);

        return $salesOrder;
    }

    /**
     * Mark SO as Under Review (reviewer starts evaluation).
     * Transitions: submitted → under_review
     */
    public function startFeasibilityReview(SalesOrder $salesOrder): SalesOrder
    {
        if ($salesOrder->status !== SalesOrderStatus::Submitted) {
            abort(422, 'Only submitted Sales Orders can start feasibility review.');
        }

        $salesOrder->update([
            'status' => SalesOrderStatus::UnderReview,
        ]);

        return $salesOrder;
    }

    /**
     * Review Feasibility.
     */
    public function reviewFeasibility(SalesOrder $salesOrder, array $data, string $reviewerId): SalesOrder
    {
        return $this->feasibilityService->reviewFeasibility($salesOrder, $data, $reviewerId);
    }

    /**
     * Accept Sales Order and trigger downstream pipeline.
     */
    public function acceptSalesOrder(SalesOrder $salesOrder, ?string $actorId = null): SalesOrder
    {
        $actor = $actorId ?? Auth::id() ?? 1;

        return $this->acceptanceService->accept($salesOrder, $actor);
    }

    /**
     * Reject Sales Order.
     */
    public function rejectSalesOrder(SalesOrder $salesOrder, ?string $reason, string $actorId): SalesOrder
    {
        if ($salesOrder->status !== SalesOrderStatus::UnderReview) {
            abort(422, 'Only sales orders under review can be rejected.');
        }

        $salesOrder->update([
            'status' => SalesOrderStatus::Rejected,
            'feasibility_status' => FeasibilityStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
        ]);

        return $salesOrder;
    }

    /**
     * Cancel Sales Order and release downstream reservations via domain services.
     *
     * Under the new architecture, reservations belong to DWO allocations, not the SO.
     * SO cancellation must cancel downstream documents through their domain services,
     * which will handle reservation release.
     *
     * If any downstream document cannot be cancelled, the SO cancellation is aborted
     * and a validation error is thrown listing all failures.
     */
    public function cancelSalesOrder(SalesOrder $salesOrder, ?string $reason, string $actorId): SalesOrder
    {
        if (! $salesOrder->canBeCancelled()) {
            abort(422, "Sales Order {$salesOrder->reference} cannot be cancelled in status '{$salesOrder->status->value}'.");
        }

        $actor = \App\Models\User::findOrFail($actorId);
        $cancelReason = $reason ?? 'Sales Order cancelled.';
        $errors = [];

        return DB::transaction(function () use ($salesOrder, $actor, $cancelReason, &$errors) {
            // 1. Cancel downstream DWOs through DWOService (handles reservation release)
            foreach ($salesOrder->deliveryWorkOrders as $dwo) {
                $dwoStatus = $dwo->status instanceof \BackedEnum ? $dwo->status->value : (string) $dwo->status;
                // Only cancel DWOs that can be cancelled (not already closed, delivered, etc.)
                if (in_array($dwoStatus, ['draft', 'planned', 'ready_to_pick', 'picking', 'ready_to_load', 'approved'], true)) {
                    try {
                        app(\App\Modules\Logistics\Services\DWOService::class)->transition($dwo, DwoStatus::Cancelled, $actor, ['notes' => $cancelReason]);
                    } catch (\Throwable $e) {
                        $errors[] = "Failed to cancel DWO {$dwo->dwo_number}: {$e->getMessage()}";
                    }
                } elseif (! in_array($dwoStatus, ['cancelled'], true)) {
                    // DWO exists in a non-cancellable state
                    $errors[] = "Cannot cancel DWO {$dwo->dwo_number}: current status is '{$dwoStatus}' which does not allow cancellation.";
                }
            }

            // 2. Cancel downstream SWOs through SwoService
            foreach ($salesOrder->serviceWorkOrders as $swo) {
                $swoStatus = $swo->status instanceof \BackedEnum ? $swo->status->value : (string) $swo->status;
                // Only cancel SWOs that can be cancelled
                if (in_array($swoStatus, ['draft', 'scheduled', 'planned', 'accepted', 'ready_for_planning'], true)) {
                    try {
                        app(\App\Modules\OperationalHub\Services\SwoService::class)->transition($swo, SwoStatus::Cancelled, $actor, ['cancellation_reason' => $cancelReason]);
                    } catch (\Throwable $e) {
                        $errors[] = "Failed to cancel SWO {$swo->swo_number}: {$e->getMessage()}";
                    }
                } elseif (! in_array($swoStatus, ['cancelled'], true)) {
                    // SWO exists in a non-cancellable state
                    $errors[] = "Cannot cancel SWO {$swo->swo_number}: current status is '{$swoStatus}' which does not allow cancellation.";
                }
            }

            // 3. If any downstream cancellation failed, abort SO cancellation
            if (! empty($errors)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'downstream_cancellation' => $errors,
                ]);
            }

            // 4. Cancel the SO itself (SO no longer owns reservations)
            $salesOrder->update([
                'status' => SalesOrderStatus::Cancelled,
                'rejection_reason' => $cancelReason,
            ]);

            return $salesOrder;
        });
    }

    public function delete(SalesOrder $salesOrder): void
    {
        $this->stateMachine->assertDeletable(
            'sales_order',
            $salesOrder->status instanceof SalesOrderStatus ? $salesOrder->status->value : (string) $salesOrder->status
        );
        $salesOrder->delete();
    }

    public function createAmendment(SalesOrder $salesOrder, array $newData, string $actorId): SalesOrder
    {
        return DB::transaction(function () use ($salesOrder, $newData) {
            $salesOrder->update(['is_current_version' => false]);

            $cloneData = $salesOrder->toArray();
            unset($cloneData['id'], $cloneData['created_at'], $cloneData['updated_at'], $cloneData['deleted_at']);

            $merged = array_merge($cloneData, $newData, [
                'parent_so_id' => $salesOrder->id,
                'version_number' => $salesOrder->version_number + 1,
                'is_current_version' => true,
                'status' => SalesOrderStatus::Draft->value,
                'feasibility_status' => FeasibilityStatus::Pending->value,
                'confirmed_at' => null,
                'confirmed_by' => null,
                'lsa_id' => null,
                'gcs_id' => null,
            ]);

            return $this->createSalesOrder($merged);
        });
    }

    /**
     * Check if all downstream work orders are complete and update SO status.
     */
    public function checkAndCloseSalesOrder(SalesOrder $salesOrder): void
    {
        $fulfillmentService = app(SalesOrderFulfillmentService::class);

        // Closed DWO must NOT automatically mean SO fulfilled.
        // Only mark completed when delivered quantities cover the commercial commitment.
        if ($fulfillmentService->isFullyFulfilled($salesOrder)) {
            if ($salesOrder->status !== SalesOrderStatus::Completed) {
                $salesOrder->update(['status' => SalesOrderStatus::Completed]);
            }
        }
    }

    /**
     * Add lines generated from an Equipment Blueprint to a draft Sales Order.
     */
    public function addLinesFromBlueprint(SalesOrder $so, array $data): SalesOrder
    {
        if (! $so->canBeEdited()) {
            abort(422, "Cannot add lines to Sales Order {$so->reference} in status '{$so->status->value}'. Order must be in draft status.");
        }

        $blueprintId = $data['blueprint_id'] ?? null;
        if (! $blueprintId) {
            abort(422, 'A blueprint_id is required.');
        }

        $blueprint = \App\Modules\Catalog\Models\EquipmentBlueprint::findOrFail($blueprintId);
        $selectedOptionValues = $data['selected_option_values'] ?? [];
        $selectedServicings = $data['selected_servicings'] ?? null;
        $variantSelections = $data['variant_selections'] ?? [];
        $equipmentQty = isset($data['equipment_qty']) ? (float) $data['equipment_qty'] : 1.0;
        $locale = $data['locale'] ?? app()->getLocale();

        $resolution = $this->blueprintLineBuilderService->resolve(
            blueprint: $blueprint,
            selectedOptionValueIds: $selectedOptionValues,
            selectedServicingIds: $selectedServicings,
            variantSelections: $variantSelections,
            equipmentQty: $equipmentQty,
            locale: $locale,
        );

        if ($resolution->hasMissingVariants()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'variant_selections' => 'Some items have multiple variants that must be selected.',
                'missing_variants' => $resolution->missingVariantSelections,
            ]);
        }

        return DB::transaction(function () use ($so, $resolution) {
            $totalHtDelta = 0.0;
            $totalTvaDelta = 0.0;
            $totalTtcDelta = 0.0;
            $hasService = $so->has_service_lines;
            $hasGoods = $so->has_goods_lines;

            foreach ($resolution->lines as $lineData) {
                $linePrice = $lineData->unitPrice;
                if (($linePrice === null || $linePrice <= 0) && ! empty($lineData->catalogItemId)) {
                    $resolved = $this->priceResolutionService->resolvePrice(
                        $lineData->catalogItemId,
                        $lineData->variantId,
                        $so->client_id,
                        $so->contract_id,
                        $so->price_list_id
                    );
                    $linePrice = $resolved['price'];
                }

                $qty = $lineData->qty;
                $effectivePrice = (float) ($linePrice ?? 0);
                $lineHt = $qty * $effectivePrice;
                $tvaRate = (float) ($lineData->tvaRatePct ?? 19.0);
                $lineTva = $lineHt * ($tvaRate / 100);
                $lineTtc = $lineHt + $lineTva;

                $type = $this->normalizeItemTypeSnapshot($so->so_type->value, $lineData->itemTypeSnapshot);
                if ($type === 'service') {
                    $hasService = true;
                } else {
                    $hasGoods = true;
                }

                $lineRecordData = [
                    'catalog_item_id' => $lineData->catalogItemId,
                    'variant_id' => $lineData->variantId,
                    'qty' => $qty,
                    'unit_id' => $lineData->unitId,
                    'unit_price' => $linePrice,
                    'discount_pct' => 0.0,
                    'total_ht' => $lineHt,
                    'total_tva' => $lineTva,
                    'total_ttc' => $lineTtc,
                    'item_type_snapshot' => $type,
                    'source_type' => 'blueprint',
                    'source_blueprint_id' => $resolution->blueprintId,
                    'source_blueprint_option_values' => $resolution->optionValueSnapshot,
                    'source_servicing_id' => $lineData->sourceServicingId,
                    'source_consumable_id' => $lineData->sourceConsumableId,
                    'calculation_snapshot' => $lineData->calculationSnapshot,
                ];

                $lineRecordData = array_merge($lineRecordData, $this->lineSnapshotService->forBlueprintLine($lineRecordData, $resolution->equipmentName));

                $so->lines()->create($lineRecordData);

                $totalHtDelta += $lineHt;
                $totalTvaDelta += $lineTva;
                $totalTtcDelta += $lineTtc;
            }

            $so->update([
                'total_ht' => round((float) $so->total_ht + $totalHtDelta, 3),
                'total_tva' => round((float) $so->total_tva + $totalTvaDelta, 3),
                'total_ttc' => round((float) $so->total_ttc + $totalTtcDelta, 3),
                'has_service_lines' => $so->isService(),
                'has_goods_lines' => $so->isSales(),
            ]);

            return $so->fresh(['lines.catalogItem', 'lines.variant', 'locations.bpAddress']);
        });
    }

    public function addLocation(SalesOrder $so, array $data): \App\Modules\Crm\Models\SalesOrderLocation
    {
        $addressId = $data['bp_address_id'];
        $isPrimary = (bool) ($data['is_primary'] ?? false);

        if ($isPrimary) {
            $so->locations()->where('is_primary', true)->update(['is_primary' => false]);
        }

        $clientSite = DB::table('client_sites')->where('bp_address_id', $addressId)->first();

        return $so->locations()->create([
            'bp_address_id' => $addressId,
            'client_site_id' => $data['client_site_id'] ?? $clientSite?->id,
            'purpose' => $data['purpose'] ?? 'delivery',
            'is_primary' => $isPrimary,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    public function removeLocation(SalesOrder $so, \App\Modules\Crm\Models\SalesOrderLocation $location): void
    {
        if ($location->sales_order_id !== $so->id) {
            abort(403, 'Location does not belong to this Sales Order.');
        }

        $location->delete();
    }

    /**
     * Validate that line item types strictly match the SO type.
     */
    private function validateLineItemTypes(string $soType, array $lines): void
    {
        foreach ($lines as $index => $line) {
            $itemType = $line['item_type_snapshot'] ?? null;

            if (! empty($line['catalog_item_id'])) {
                $catalogItem = CatalogItem::find($line['catalog_item_id']);
                if ($catalogItem) {
                    $itemType = $catalogItem->item_type ?? $catalogItem->type ?? $itemType;
                }
            }

            $isService = in_array($itemType, ['service', 'eq_servicing'], true);

            if ($soType === 'sales') {
                if ($isService) {
                    abort(422, 'Line '.($index + 1).': Cannot add a service item to a Sales-type Sales Order. Sales SOs only accept physical goods.');
                }
            } elseif ($soType === 'service') {
                // Service SOs accept service items and servicing consumables/spare parts
                $isAllowed = in_array($itemType, ['service', 'eq_servicing', 'spare_part', 'consumable'], true);
                if (! $isAllowed && ! empty($itemType)) {
                    abort(422, 'Line '.($index + 1).': Cannot add a physical commercial product to a Service-type Sales Order. Service SOs only accept service items and consumables.');
                }
            }
        }
    }

    /**
     * Normalize item_type_snapshot to match sales_order_lines enum ('physical_product', 'service', 'spare_part').
     */
    private function normalizeItemTypeSnapshot(string $soType, ?string $type): string
    {
        return match ($type) {
            'service', 'eq_servicing' => 'service',
            'spare_part', 'consumable' => 'spare_part',
            'physical_product', 'product' => 'physical_product',
            default => $soType === 'service' ? 'service' : 'physical_product',
        };
    }
}
