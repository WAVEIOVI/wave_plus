<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Modules\Logistics\Enums\DwoLineStatus;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\DwoLine;
use App\Modules\Logistics\Models\PickingList;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DwoPickingService
{
    /**
     * Record picking confirmation for an individual DWO line.
     */
    public function recordPick(DwoLine $line, array $data, string $performedBy): DwoLine
    {
        $qtyPicked = (float) ($data['qty_picked'] ?? 0);
        $qtyAllocated = (float) $line->qty_allocated;

        if ($qtyPicked < 0) {
            throw ValidationException::withMessages([
                'qty_picked' => ['Picked quantity cannot be negative.'],
            ]);
        }

        if ($qtyPicked > $qtyAllocated) {
            throw ValidationException::withMessages([
                'qty_picked' => ["Picked quantity ({$qtyPicked}) cannot exceed allocated quantity ({$qtyAllocated})."],
            ]);
        }

        // Validate that the DWO is in Picking status
        $dwo = $line->deliveryWorkOrder;
        if (!$dwo || $dwo->status !== DwoStatus::Picking) {
            throw ValidationException::withMessages([
                'dwo_id' => ['Picking can only be performed on a DWO in Picking status.'],
            ]);
        }

        return DB::transaction(function () use ($line, $data, $qtyPicked, $qtyAllocated, $performedBy) {
            $line->qty_picked = $qtyPicked;

            if (isset($data['lot_number'])) {
                $line->lot_number = $data['lot_number'];
            }
            if (isset($data['serial_number'])) {
                $line->serial_number = $data['serial_number'];
            }
            if (isset($data['warehouse_location_id'])) {
                $line->warehouse_location_id = $data['warehouse_location_id'];
            }
            if (isset($data['driver_notes'])) {
                $line->driver_notes = $data['driver_notes'];
            }

            $line->item_status = ($qtyPicked >= $qtyAllocated && $qtyAllocated > 0)
                ? DwoLineStatus::Picked
                : DwoLineStatus::Pending;

            $line->updated_by = $performedBy;
            $line->save();

            return $line;
        });
    }

    /**
     * Batch confirm picks for multiple lines on a DWO.
     */
    public function batchRecordPicks(DeliveryWorkOrder $dwo, array $picks, string $performedBy): void
    {
        // Validate that the DWO is in Picking status
        if ($dwo->status !== DwoStatus::Picking) {
            throw ValidationException::withMessages([
                'dwo_id' => ['Picking can only be performed on a DWO in Picking status.'],
            ]);
        }

        DB::transaction(function () use ($dwo, $picks, $performedBy) {
            foreach ($picks as $item) {
                $lineId = $item['line_id'] ?? null;
                /** @var DwoLine|null $line */
                $line = $dwo->lines()->where('id', $lineId)->first();
                if ($line) {
                    $this->recordPick($line, $item, $performedBy);
                }
            }
        });
    }

    /**
     * Check if picking is 100% complete across all lines.
     */
    public function isPickingComplete(DeliveryWorkOrder $dwo): bool
    {
        $dwo->loadMissing('lines');

        // Only consider lines that actually have an allocation to pick
        $allocatedLines = $dwo->lines->filter(fn (DwoLine $line) => (float) $line->qty_allocated > 0);

        // A DWO with no allocated lines is not ready to load
        if ($allocatedLines->isEmpty()) {
            return false;
        }

        return $allocatedLines->every(
            fn (DwoLine $line) => (float) $line->qty_picked >= (float) $line->qty_allocated,
        );
    }

    /**
     * Complete a picking list for a DWO.
     * Validates that the DWO is in Picking status and all lines are fully picked.
     */
    public function completePickingList(DeliveryWorkOrder $dwo, string $performedBy): PickingList
    {
        // Validate that the DWO is in Picking status
        if ($dwo->status !== DwoStatus::Picking) {
            throw ValidationException::withMessages([
                'dwo_id' => ['Picking list can only be completed for a DWO in Picking status.'],
            ]);
        }

        // Validate that all lines are fully picked
        if (!$this->isPickingComplete($dwo)) {
            throw ValidationException::withMessages([
                'dwo_id' => ['Picking list can only be completed when all lines are fully picked.'],
            ]);
        }

        $pickingList = $dwo->pickingList;
        if (!$pickingList) {
            throw ValidationException::withMessages([
                'dwo_id' => ['No picking list found for this DWO.'],
            ]);
        }

        return DB::transaction(function () use ($pickingList, $performedBy) {
            $pickingList->update([
                'status'       => 'completed',
                'completed_at' => now(),
                'updated_by'   => $performedBy,
            ]);

            return $pickingList->fresh();
        });
    }
}
