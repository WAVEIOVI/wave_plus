<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Models\User;
use App\Modules\Logistics\Enums\DeliveryExceptionReason;
use App\Modules\Logistics\Models\DeliveryException;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryExceptionService
{
    /**
     * Record a delivery exception on a DWO.
     */
    public function recordException(DeliveryWorkOrder $dwo, array $data, User $actor): DeliveryException
    {
        $reasonValue = $data['reason'] ?? null;
        $reason = DeliveryExceptionReason::tryFrom((string) $reasonValue);

        if (!$reason) {
            throw ValidationException::withMessages([
                'reason' => ['A valid exception reason must be specified.'],
            ]);
        }

        return DB::transaction(function () use ($dwo, $data, $reason, $actor) {
            $exception = DeliveryException::create([
                'dwo_id'       => $dwo->id,
                'dwo_line_id'  => $data['dwo_line_id'] ?? null,
                'reason'       => $reason,
                'affected_qty' => isset($data['affected_qty']) ? (float) $data['affected_qty'] : null,
                'notes'        => $data['notes'] ?? null,
                'status'       => 'open',
                'created_by'   => $actor->id,
                'updated_by'   => $actor->id,
            ]);

            $dwo->exception_at = now();
            $dwo->save();

            return $exception;
        });
    }

    /**
     * Resolve an existing delivery exception.
     */
    public function resolveException(DeliveryException $exception, array $data, User $actor): DeliveryException
    {
        return DB::transaction(function () use ($exception, $data, $actor) {
            $exception->update([
                'resolution_notes' => $data['resolution_notes'] ?? 'Resolved',
                'status'           => 'resolved',
                'resolved_at'      => now(),
                'resolved_by'      => $actor->id,
                'updated_by'       => $actor->id,
            ]);

            // If no more open exceptions on the DWO, clear exception_at if appropriate
            $dwo = $exception->deliveryWorkOrder;
            $hasRemainingOpen = $dwo->exceptions()->where('status', 'open')->exists();
            if (!$hasRemainingOpen && $dwo->status->value === 'exception') {
                // Keep history, but allow progress
            }

            return $exception;
        });
    }
}
