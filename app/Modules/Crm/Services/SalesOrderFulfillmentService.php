<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Models\SalesOrderLine;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Modules\Logistics\Models\DwoLine;
use App\Modules\OperationalHub\Models\SwoItem;
use Illuminate\Support\Collection;

/**
 * Authoritative SO fulfillment tracker.
 *
 * Compares commercial commitment (SO) against downstream realization (DWO/SWO).
 * Does NOT execute fulfillment — Logistics / Operational Hub own execution.
 */
class SalesOrderFulfillmentService
{
    /**
     * High-level fulfillment summary for a Sales Order.
     *
     * @return array{
     *     so_id: string,
     *     so_type: string,
     *     status: string,
     *     ordered_qty: float,
     *     allocated_qty: float,
     *     reserved_qty: float,
     *     delivered_qty: float,
     *     remaining_qty: float,
     *     is_fully_fulfilled: bool,
     *     lines: array<int, array<string, mixed>>,
     *     exceptions: array<int, array<string, mixed>>,
     * }
     */
    public function getFulfillmentSummary(SalesOrder $so): array
    {
        $so->load(['lines', 'deliveryWorkOrders.lines', 'serviceWorkOrders']);

        if ($so->isService()) {
            return $this->serviceSummary($so);
        }

        return $this->goodsSummary($so);
    }

    /**
     * Per-line fulfillment for a goods SO line.
     *
     * @return array{
     *     so_line_id: string,
     *     ordered_qty: float,
     *     allocated_qty: float,
     *     reserved_qty: float,
     *     delivered_qty: float,
     *     remaining_qty: float,
     *     shortage_qty: float,
     *     is_fully_fulfilled: bool,
     * }
     */
    public function getLineFulfillment(SalesOrderLine $line): array
    {
        $ordered = (float) $line->qty;

        $activeDwoLines = DwoLine::query()
            ->where('so_item_id', $line->id)
            ->whereHas('deliveryWorkOrder', function ($q) {
                $q->whereNotIn('status', [
                    DwoStatus::Cancelled->value,
                    DwoStatus::Closed->value,
                    DwoStatus::Delivered->value,
                    DwoStatus::PartiallyDelivered->value,
                    DwoStatus::DeliveryFailed->value,
                ]);
            })
            ->get();

        $allocated = (float) $activeDwoLines->sum('qty_allocated');
        $reserved = (float) $activeDwoLines->sum('qty_reserved');
        $delivered = (float) $activeDwoLines->sum('qty_delivered');
        $shortage = (float) $activeDwoLines->sum('qty_shortage');
        $remaining = max(0.0, round($ordered - $delivered, 3));

        return [
            'so_line_id'         => $line->id,
            'ordered_qty'        => $ordered,
            'allocated_qty'      => round($allocated, 3),
            'reserved_qty'       => round($reserved, 3),
            'delivered_qty'      => round($delivered, 3),
            'remaining_qty'      => $remaining,
            'shortage_qty'       => round($shortage, 3),
            'is_fully_fulfilled' => $remaining <= 0.0001,
        ];
    }

    public function getRemainingQuantity(SalesOrderLine $line): float
    {
        return $this->getLineFulfillment($line)['remaining_qty'];
    }

    public function isFullyFulfilled(SalesOrder $so): bool
    {
        $summary = $this->getFulfillmentSummary($so);

        return (bool) $summary['is_fully_fulfilled'];
    }

    /**
     * Derived fulfillment status string for API/UI compatibility.
     * Based on delivered quantities vs commercial commitment — NOT on DWO closed status alone.
     * These are DERIVED fulfillment states, distinct from SO lifecycle status.
     */
    public function calculateFulfillmentStatus(SalesOrder $so): string
    {
        return $this->getFulfillmentSummary($so)['status'];
    }

    /**
     * @return array<string, mixed>
     */
    private function goodsSummary(SalesOrder $so): array
    {
        $lines = $so->lines
            ->filter(function (SalesOrderLine $line) {
                $type = $line->item_type_snapshot instanceof \BackedEnum
                    ? $line->item_type_snapshot->value
                    : (string) ($line->item_type_snapshot ?? '');

                return $type !== 'service' && $type !== 'eq_servicing';
            })
            ->values();

        $lineSummaries = $lines->map(fn (SalesOrderLine $line) => $this->getLineFulfillment($line));

        $ordered = (float) $lineSummaries->sum('ordered_qty');
        $allocated = (float) $lineSummaries->sum('allocated_qty');
        $reserved = (float) $lineSummaries->sum('reserved_qty');
        $delivered = (float) $lineSummaries->sum('delivered_qty');
        $remaining = (float) $lineSummaries->sum('remaining_qty');

        $exceptions = $this->collectGoodsExceptions($so);

        return [
            'so_id'              => $so->id,
            'so_type'            => $so->so_type->value,
            'status'             => $this->statusFromQuantities($ordered, $delivered, $allocated),
            'ordered_qty'        => round($ordered, 3),
            'allocated_qty'      => round($allocated, 3),
            'reserved_qty'       => round($reserved, 3),
            'delivered_qty'      => round($delivered, 3),
            'remaining_qty'      => round($remaining, 3),
            'is_fully_fulfilled' => $ordered > 0 && $remaining <= 0.0001,
            'lines'              => $lineSummaries->all(),
            'exceptions'         => $exceptions,
            'dwo_count'          => $so->deliveryWorkOrders->count(),
        ];
    }

    /**
     * Service SO: high-level operational summary using SWO item completion data.
     *
     * @return array<string, mixed>
     */
    private function serviceSummary(SalesOrder $so): array
    {
        $so->loadMissing(['lines', 'serviceWorkOrders.items']);

        $swos = $so->serviceWorkOrders;
        $ordered = (float) $so->lines->sum('qty');

        // Get all SWO items for this SO to calculate actual completion
        $swoItems = SwoItem::query()
            ->whereHas('serviceWorkOrder', function ($q) use ($so) {
                $q->where('sales_order_id', $so->id)
                    ->whereNotIn('status', ['cancelled']);
            })
            ->with('serviceWorkOrder')
            ->get();

        // Calculate per-line fulfillment from SWO items
        $lineSummaries = [];
        $totalCompleted = 0.0;
        $totalPlanned = 0.0;

        foreach ($so->lines as $line) {
            $lineItems = $swoItems->where('so_line_id', $line->id);
            $linePlanned = (float) $lineItems->sum('qty_ordered');
            $lineCompleted = (float) $lineItems->sum('qty_completed');
            $lineRemaining = max(0.0, (float) $line->qty - $lineCompleted);

            $totalPlanned += $linePlanned;
            $totalCompleted += $lineCompleted;

            // Determine line status
            $lineStatus = 'pending';
            if ($lineCompleted >= (float) $line->qty - 0.0001) {
                $lineStatus = 'complete';
            } elseif ($lineCompleted > 0.0001) {
                $lineStatus = 'in_progress';
            } elseif ($linePlanned > 0.0001) {
                $lineStatus = 'scheduled';
            }

            $lineSummaries[] = [
                'so_line_id'         => $line->id,
                'ordered_qty'        => (float) $line->qty,
                'planned_qty'        => round($linePlanned, 3),
                'completed_qty'      => round($lineCompleted, 3),
                'remaining_qty'      => round($lineRemaining, 3),
                'fulfillment_status' => $lineStatus,
                'is_fully_fulfilled' => $lineRemaining <= 0.0001,
            ];
        }

        // Overall SWO status counts
        $completedCount = 0;
        $inProgressCount = 0;
        $scheduledCount = 0;

        foreach ($swos as $swo) {
            $status = $swo->status instanceof \BackedEnum ? $swo->status->value : (string) data_get($swo, 'status');
            if (in_array($status, ['completed', 'closed'], true)) {
                $completedCount++;
            } elseif (in_array($status, ['in_progress', 'scheduled', 'assigned', 'planned', 'accepted', 'ready_for_planning', 'partially_completed'], true)) {
                $inProgressCount++;
            } elseif (in_array($status, ['draft', 'planned'], true)) {
                $scheduledCount++;
            }
        }

        // Overall fulfillment status - using distinct terminology from SO lifecycle
        $isFully = $ordered > 0 && $totalCompleted >= $ordered - 0.0001;
        $status = 'pending';
        if ($isFully) {
            $status = 'complete';
        } elseif ($totalCompleted > 0.0001 || $totalPlanned > 0.0001) {
            $status = 'in_progress';
        }

        // Collect exceptions
        $exceptions = $this->collectServiceExceptions($so, $swoItems);

        return [
            'so_id'              => $so->id,
            'so_type'            => $so->so_type->value,
            'status'             => $status,
            'ordered_qty'        => round($ordered, 3),
            'planned_qty'        => round($totalPlanned, 3),
            'completed_qty'      => round($totalCompleted, 3),
            'remaining_qty'      => round(max(0.0, $ordered - $totalCompleted), 3),
            'is_fully_fulfilled' => $isFully,
            'lines'              => $lineSummaries,
            'exceptions'         => $exceptions,
            'swo_count'          => $swos->count(),
            'missions_completed' => $completedCount,
            'missions_in_progress' => $inProgressCount,
            'missions_scheduled' => $scheduledCount,
            'progress'           => $ordered > 0 ? round(($totalCompleted / $ordered) * 100) : 0,
        ];
    }

    /**
     * Collect service exceptions (overdue, incomplete scope, etc.)
     *
     * @param  \Illuminate\Support\Collection<int, \App\Modules\OperationalHub\Models\SwoItem>  $swoItems
     * @return array<int, array<string, mixed>>
     */
    private function collectServiceExceptions(SalesOrder $so, Collection $swoItems): array
    {
        $exceptions = [];

        // Check for SWOs with no items or zero planned quantities
        foreach ($so->serviceWorkOrders as $swo) {
            $status = $swo->status instanceof \BackedEnum ? $swo->status->value : (string) data_get($swo, 'status');
            $swoItemsForSwo = $swoItems->where('swo_id', $swo->id);

            if ($swoItemsForSwo->isEmpty()) {
                $exceptions[] = [
                    'type'          => 'no_scope',
                    'swo_id'        => $swo->id,
                    'swo_number'    => $swo->swo_number,
                    'message'       => 'SWO has no service scope items defined.',
                ];
            } elseif ($swoItemsForSwo->sum('qty_ordered') <= 0.0001) {
                $exceptions[] = [
                    'type'          => 'zero_scope',
                    'swo_id'        => $swo->id,
                    'swo_number'    => $swo->swo_number,
                    'message'       => 'SWO scope items have zero planned quantity.',
                ];
            }

            // Check for overdue SWOs
            if (in_array($status, ['scheduled', 'assigned', 'planned', 'in_progress'], true) &&
                $swo->planned_end && now()->gt($swo->planned_end)) {
                $exceptions[] = [
                    'type'          => 'overdue',
                    'swo_id'        => $swo->id,
                    'swo_number'    => $swo->swo_number,
                    'planned_end'   => $swo->planned_end?->toDateString(),
                    'message'       => 'SWO is overdue.',
                ];
            }
        }

        return $exceptions;
    }

    private function statusFromQuantities(float $ordered, float $delivered, float $allocated): string
    {
        if ($ordered <= 0.0001) {
            return 'pending';
        }
        if ($delivered + 0.0001 >= $ordered) {
            return 'complete';
        }
        if ($delivered > 0.0001 || $allocated > 0.0001) {
            return 'in_progress';
        }

        return 'pending';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectGoodsExceptions(SalesOrder $so): array
    {
        $exceptions = [];

        foreach ($so->deliveryWorkOrders as $dwo) {
            $status = $dwo->status instanceof \BackedEnum ? $dwo->status->value : (string) $dwo->status;
            if (! in_array($status, ['closed', 'partially_delivered', 'delivery_failed', 'exception'], true)) {
                continue;
            }

            foreach ($dwo->lines as $line) {
                $undelivered = max(0.0, (float) $line->qty_allocated - (float) ($line->qty_delivered ?? 0));
                if ($undelivered > 0.0001) {
                    $exceptions[] = [
                        'type'           => 'quantity_short',
                        'dwo_id'         => $dwo->id,
                        'dwo_number'     => $dwo->dwo_number,
                        'dwo_line_id'    => $line->id,
                        'so_line_id'     => $line->so_item_id,
                        'undelivered_qty'=> round($undelivered, 3),
                        'dwo_status'     => $status,
                    ];
                }

                if ($line->substitution_status === 'proposed' || $line->substitution_status === 'approved') {
                    $exceptions[] = [
                        'type'              => 'catalog_substitution',
                        'dwo_id'            => $dwo->id,
                        'dwo_line_id'       => $line->id,
                        'so_line_id'        => $line->so_item_id,
                        'commercial_item'   => $line->commercial_catalog_item_id,
                        'commercial_variant'=> $line->commercial_catalog_variant_id,
                        'actual_item'       => $line->catalog_item_id,
                        'actual_variant'    => $line->catalog_variant_id,
                        'status'            => $line->substitution_status,
                        'reason'            => $line->substitution_reason,
                    ];
                }
            }
        }

        return $exceptions;
    }
}
