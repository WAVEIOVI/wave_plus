<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Modules\Logistics\Enums\DwoLineStatus;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Marks a DWO as operationally dispatched.
 *
 * Does NOT post stock movements. Physical dispatch stock is owned by
 * Delivery Note confirmation (DeliveryNoteStockService).
 */
class DwoDispatchService
{
    /**
     * Record dispatch timestamps and line status. No inventory ledger writes.
     */
    public function dispatchDwo(DeliveryWorkOrder $dwo, string $performedBy): array
    {
        return DB::transaction(function () use ($dwo) {
            $dwo->loadMissing('lines');

            foreach ($dwo->lines as $line) {
                $line->item_status = DwoLineStatus::Dispatched;
                $line->save();
            }

            $dwo->dispatched_at = $dwo->dispatched_at ?? now();
            $dwo->save();

            Log::info('[Logistics] DWO dispatched (no stock movement)', [
                'dwo_id'     => $dwo->id,
                'dwo_number' => $dwo->dwo_number,
            ]);

            return [];
        });
    }
}
