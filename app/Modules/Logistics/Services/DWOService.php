<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Models\User;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Services\SalesOrderService;
use App\Modules\Logistics\Enums\DwoLineStatus;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Modules\Logistics\Events\DeliveryCompleted;
use App\Modules\Logistics\Events\DWOStatusChanged;
use App\Modules\Logistics\Models\DeliveryNote;
use App\Modules\Logistics\Models\DeliveryStatusHistory;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\DwoLine;
use App\Modules\Hr\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DWOService
{
    public function __construct(
        private readonly SalesOrderService $salesOrderService,
        private readonly DwoReservationService $reservationService,
        private readonly DwoPickingService $pickingService,
        private readonly DwoDispatchService $dispatchService,
        private readonly PodService $podService,
        private readonly DeliveryExceptionService $exceptionService,
    ) {}

    /**
     * Transition a DWO to a new status, enforcing state machine guards and domain workflows.
     */
    public function transition(
        DeliveryWorkOrder $dwo,
        DwoStatus|string $targetStatus,
        User $actor,
        array $payload = []
    ): DeliveryWorkOrder {
        $target = $targetStatus instanceof DwoStatus
            ? $targetStatus
            : (DwoStatus::tryFrom((string) $targetStatus) ?? throw ValidationException::withMessages([
                'status' => ["Unknown status '{$targetStatus}'."],
            ]));

        return DB::transaction(function () use ($dwo, $target, $actor, $payload) {
            $current = $dwo->status;

            if ($current === $target) {
                return $dwo;
            }

            // 1. Check allowed transition graph
            if (! $current->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => ["Cannot transition Delivery Work Order from '{$current->label()}' ({$current->value}) to '{$target->label()}' ({$target->value})."],
                ]);
            }

            // 2. Validate transition specific guards & side effects
            $this->applyTransitionRules($dwo, $current, $target, $actor, $payload);

            // 3. Update status & tracking
            $oldStatusValue = $current->value;
            $dwo->status = $target;
            $dwo->updated_by = $actor->id;
            $dwo->save();

            // 4. Record status history
            DeliveryStatusHistory::create([
                'dwo_id' => $dwo->id,
                'from_status' => $current,
                'to_status' => $target,
                'changed_by' => $actor->id,
                'changed_at' => now(),
                'notes' => $payload['notes'] ?? "Transitioned to {$target->label()}",
                'metadata' => ! empty($payload) ? $payload : null,
            ]);

            // 5. Fire event
            event(new DWOStatusChanged($dwo, $oldStatusValue, $target->value, $actor));

            return $dwo->fresh(['lines', 'pod', 'exceptions', 'warehouse', 'deliveryAddress', 'vehicle', 'driver']);
        });
    }

    protected function applyTransitionRules(
        DeliveryWorkOrder $dwo,
        DwoStatus $from,
        DwoStatus $to,
        User $actor,
        array $payload
    ): void {
        switch ($to) {
            case DwoStatus::Planned:
                if (! $dwo->delivery_bp_address_id && ! $dwo->delivery_site_id) {
                    throw ValidationException::withMessages([
                        'status' => ['A delivery address or site is required to plan this order.'],
                    ]);
                }
                if (! empty($payload['approved_driver_id'])) {
                    $dwo->approved_driver_id = $payload['approved_driver_id'];
                }
                if (! empty($payload['vehicle_id'])) {
                    $dwo->vehicle_id = $payload['vehicle_id'];
                }
                if (! empty($payload['planned_date'])) {
                    $dwo->planned_date = $payload['planned_date'];
                }
                break;

            case DwoStatus::ReadyToPick:
                // Reserve physical inventory for DWO lines
                $this->reservationService->reserveForDwo($dwo, $actor->id);
                break;

            case DwoStatus::Picking:
                if ($dwo->lines()->count() === 0) {
                    throw ValidationException::withMessages([
                        'status' => ['Cannot start picking: DWO has no items.'],
                    ]);
                }
                break;

            case DwoStatus::ReadyToLoad:
                // Verify all lines are picked
                if (! $this->pickingService->isPickingComplete($dwo)) {
                    throw ValidationException::withMessages([
                        'status' => ['Cannot set Ready to Load: picking is incomplete. All items must be picked first.'],
                    ]);
                }
                break;

            case DwoStatus::Loaded:
                // Optional vehicle capacity check & vehicle assignment
                if (! empty($payload['vehicle_id'])) {
                    $dwo->vehicle_id = $payload['vehicle_id'];
                }
                if (! empty($payload['approved_driver_id'])) {
                    $dwo->approved_driver_id = $payload['approved_driver_id'];
                }

                if (! $dwo->vehicle_id) {
                    throw ValidationException::withMessages([
                        'vehicle_id' => ['A vehicle must be assigned before marking the order as Loaded.'],
                    ]);
                }

                // Check vehicle status and capacity
                $vehicle = Vehicle::find($dwo->vehicle_id);
                if ($vehicle && $vehicle->capacity_kg) {
                    $totalWeight = $this->calculateTotalWeight($dwo);
                    if ($totalWeight > (float) $vehicle->capacity_kg) {
                        throw ValidationException::withMessages([
                            'vehicle_id' => ["Total shipment weight ({$totalWeight} kg) exceeds vehicle capacity ({$vehicle->capacity_kg} kg)."],
                        ]);
                    }
                }

                // Set qty_loaded = qty_picked for all lines
                foreach ($dwo->lines as $line) {
                    $line->qty_loaded = $line->qty_picked > 0 ? $line->qty_picked : $line->qty_allocated;
                    $line->item_status = DwoLineStatus::Loaded;
                    $line->save();
                }
                break;

            case DwoStatus::Dispatched:
                // Issue goods via stock movements ledger & fulfill reservations
                $this->dispatchService->dispatchDwo($dwo, $actor->id);
                break;

            case DwoStatus::InTransit:
                if (! empty($payload['eta'])) {
                    $dwo->eta = $payload['eta'];
                }
                $this->ensureDeliveryNoteCreated($dwo, $actor);
                break;

            case DwoStatus::Delivered:
                $dwo->loadMissing('deliveryNote');

                if (! $dwo->deliveryNote || $dwo->deliveryNote->status !== 'confirmed') {
                    throw ValidationException::withMessages([
                        'status' => ['Cannot mark as Delivered: a confirmed Delivery Note is required. Confirm the Delivery Note first.'],
                    ]);
                }

                $dwo->delivered_at = now();

                // Record Proof of Delivery if provided in payload
                if (! empty($payload['pod'])) {
                    $this->podService->recordPod($dwo, $payload['pod'], $actor);
                }

                // NOTE: Line delivered quantities are now updated by DeliveryNoteStockService::confirmAndPost()
                // when the Delivery Note is confirmed. Do NOT set qty_delivered here.

                event(new DeliveryCompleted($dwo));
                break;

            case DwoStatus::PartiallyDelivered:
                $dwo->loadMissing('deliveryNote');

                if (! $dwo->deliveryNote || $dwo->deliveryNote->status !== 'confirmed') {
                    throw ValidationException::withMessages([
                        'status' => ['Cannot mark as Partially Delivered: a confirmed Delivery Note is required. Confirm the Delivery Note first.'],
                    ]);
                }

                $dwo->delivered_at = now();

                if (! empty($payload['pod'])) {
                    $this->podService->recordPod($dwo, $payload['pod'], $actor);
                }
                if (! empty($payload['exception'])) {
                    $this->exceptionService->recordException($dwo, $payload['exception'], $actor);
                }

                // NOTE: Partial line quantities are updated by DeliveryNoteStockService::confirmAndPost()
                // when the Delivery Note is confirmed. Do NOT set qty_delivered here.

                break;

            case DwoStatus::DeliveryFailed:
                if (! empty($payload['exception'])) {
                    $this->exceptionService->recordException($dwo, $payload['exception'], $actor);
                }
                foreach ($dwo->lines as $line) {
                    $line->item_status = DwoLineStatus::Failed;
                    $line->save();
                }
                break;

            case DwoStatus::Closed:
                $dwo->closed_at = now();

                // Release leftover reservations; closing DWO does not fulfill the SO.
                $this->reservationService->releaseUndeliveredForDwo($dwo, $actor->id);

                if ($dwo->sales_order_id) {
                    $salesOrder = SalesOrder::find($dwo->sales_order_id);
                    if ($salesOrder) {
                        $this->salesOrderService->checkAndCloseSalesOrder($salesOrder);
                    }
                }
                break;

            case DwoStatus::Cancelled:
                // Release stock reservations
                $this->reservationService->releaseForDwo($dwo, $actor->id);

                foreach ($dwo->lines as $line) {
                    $line->item_status = DwoLineStatus::Cancelled;
                    $line->save();
                }
                break;

            case DwoStatus::Exception:
                $dwo->exception_at = now();
                if (! empty($payload['exception'])) {
                    $this->exceptionService->recordException($dwo, $payload['exception'], $actor);
                }
                break;
        }
    }

    public function ensureDeliveryNoteCreated(DeliveryWorkOrder $dwo, User $actor): DeliveryNote
    {
        $dwo->loadMissing('deliveryNote');

        if (! $dwo->deliveryNote) {
            $dnNumber = str_replace('DWO-', 'DN-', $dwo->dwo_number);
            if ($dnNumber === $dwo->dwo_number) {
                $dnNumber = 'DN-'.$dwo->dwo_number;
            }

            return DeliveryNote::create([
                'dn_number' => $dnNumber,
                'dwo_id' => $dwo->id,
                'client_id' => $dwo->client_id,
                'delivery_date' => now(),
                'status' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        }

        return $dwo->deliveryNote;
    }

    private function calculateTotalWeight(DeliveryWorkOrder $dwo): float
    {
        $dwo->loadMissing(['lines.catalogItem']);
        $totalWeight = 0.0;

        foreach ($dwo->lines as $line) {
            $qty = (float) ($line->qty_picked > 0 ? $line->qty_picked : $line->qty_allocated);
            // Default 1kg per unit if not defined
            $unitWeight = (float) ($line->catalogItem?->gross_weight_kg ?? 1.0);
            $totalWeight += ($qty * $unitWeight);
        }

        return round($totalWeight, 2);
    }

    /**
     * Approve a proposed substitution on a DWO line.
     *
     * When approved:
     * - The actual catalog_item_id/catalog_variant_id becomes the proposed item
     * - substitution_status changes to 'approved'
     * - substitution_approved_by/at are set
     * - Existing reservations are released and re-reserved against the new item
     */
    public function approveSubstitution(DwoLine $line, User $actor): DwoLine
    {
        if ($line->substitution_status !== 'proposed') {
            throw ValidationException::withMessages([
                'substitution' => ['Only proposed substitutions can be approved.'],
            ]);
        }

        if (! $line->proposed_catalog_item_id && ! $line->proposed_catalog_variant_id) {
            throw ValidationException::withMessages([
                'substitution' => ['No proposed item to approve.'],
            ]);
        }

        return DB::transaction(function () use ($line, $actor) {
            // Release existing reservations for THIS LINE ONLY (not the entire DWO)
            app(\App\Modules\Logistics\Services\DwoReservationService::class)->releaseForLine($line->deliveryWorkOrder, $line, $actor->id);

            // Switch actual item to proposed item
            $line->catalog_item_id = $line->proposed_catalog_item_id ?? $line->catalog_item_id;
            $line->catalog_variant_id = $line->proposed_catalog_variant_id ?? $line->catalog_variant_id;
            $line->substitution_status = 'approved';
            $line->substitution_approved_by = $actor->id;
            $line->substitution_approved_at = now();
            $line->save();

            // Re-reserve against the newly approved item
            $line->loadMissing('catalogItem');
            app(\App\Modules\Logistics\Services\DwoReservationService::class)->reserveForLine($line->deliveryWorkOrder, $line, $actor->id);

            return $line->fresh(['catalogItem', 'catalogVariant', 'deliveryWorkOrder']);
        });
    }

    /**
     * Reject a proposed substitution on a DWO line.
     *
     * When rejected:
     * - The actual item remains the commercial item (no change)
     * - substitution_status changes to 'rejected'
     * - substitution_approved_by/at are set for audit
     * - Proposed item remains stored for audit trail
     */
    public function rejectSubstitution(DwoLine $line, User $actor, ?string $reason = null): DwoLine
    {
        if ($line->substitution_status !== 'proposed') {
            throw ValidationException::withMessages([
                'substitution' => ['Only proposed substitutions can be rejected.'],
            ]);
        }

        return DB::transaction(function () use ($line, $actor, $reason) {
            $line->substitution_status = 'rejected';
            $line->substitution_approved_by = $actor->id;
            $line->substitution_approved_at = now();
            if ($reason) {
                $line->substitution_reason = $reason;
            }
            $line->save();

            return $line->fresh();
        });
    }
}
