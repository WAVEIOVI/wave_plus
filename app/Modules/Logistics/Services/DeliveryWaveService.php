<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Services;

use App\Models\User;
use App\Modules\Logistics\Enums\DeliveryStopStatus;
use App\Modules\Logistics\Enums\DeliveryWaveStatus;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Modules\Logistics\Models\DeliveryStop;
use App\Modules\Logistics\Models\DeliveryWave;
use App\Modules\Logistics\Models\DeliveryWaveItem;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryWaveService
{
    public function __construct(
        private readonly DWOService $dwoService,
    ) {}

    /**
     * Create a new Delivery Wave and optionally assign initial DWOs.
     */
    public function createWave(array $data, User $actor): DeliveryWave
    {
        return DB::transaction(function () use ($data, $actor) {
            $wave = new DeliveryWave();
            $wave->fill([
                'planned_date'  => $data['planned_date'] ?? now()->toDateString(),
                'dispatch_time' => $data['dispatch_time'] ?? null,
                'warehouse_id'  => $data['warehouse_id'] ?? null,
                'vehicle_id'    => $data['vehicle_id'] ?? null,
                'approved_driver_id'    => $data['approved_driver_id'] ?? null,
                'route_notes'   => $data['route_notes'] ?? null,
                'status'        => DeliveryWaveStatus::Draft,
                'created_by'    => $actor->id,
                'updated_by'    => $actor->id,
            ]);
            $wave->save();

            if (!empty($data['dwo_ids']) && is_array($data['dwo_ids'])) {
                foreach ($data['dwo_ids'] as $index => $dwoId) {
                    $dwo = DeliveryWorkOrder::find($dwoId);
                    if ($dwo) {
                        $this->addDwoToWave($wave, $dwo, $index + 1);
                    }
                }
            }

            return $wave->load(['waveItems.deliveryWorkOrder.client', 'stops.deliveryAddress', 'vehicle', 'approvedDriver', 'warehouse']);
        });
    }

    /**
     * Add a DWO to a Delivery Wave and create a corresponding stop.
     */
    public function addDwoToWave(DeliveryWave $wave, DeliveryWorkOrder $dwo, ?int $sequence = null): void
    {
        DB::transaction(function () use ($wave, $dwo, $sequence) {
            $seq = $sequence ?? ($wave->waveItems()->max('sequence') ?? 0) + 1;

            DeliveryWaveItem::updateOrCreate(
                ['wave_id' => $wave->id, 'dwo_id' => $dwo->id],
                ['sequence' => $seq]
            );

            $dwo->wave_id = $wave->id;
            if ($wave->vehicle_id && !$dwo->vehicle_id) {
                $dwo->vehicle_id = $wave->vehicle_id;
            }
            if ($wave->approved_driver_id && !$dwo->approved_driver_id) {
                $dwo->approved_driver_id = $wave->approved_driver_id;
            }
            $dwo->save();

            // Create or update stop
            DeliveryStop::updateOrCreate(
                ['wave_id' => $wave->id, 'dwo_id' => $dwo->id],
                [
                    'sequence'               => $seq,
                    'delivery_bp_address_id' => $dwo->delivery_bp_address_id,
                    'status'                 => DeliveryStopStatus::Pending,
                ]
            );
        });
    }

    /**
     * Remove a DWO from a Delivery Wave.
     */
    public function removeDwoFromWave(DeliveryWave $wave, DeliveryWorkOrder $dwo): void
    {
        DB::transaction(function () use ($wave, $dwo) {
            DeliveryWaveItem::where('wave_id', $wave->id)->where('dwo_id', $dwo->id)->delete();
            DeliveryStop::where('wave_id', $wave->id)->where('dwo_id', $dwo->id)->delete();

            if ($dwo->wave_id === $wave->id) {
                $dwo->wave_id = null;
                $dwo->save();
            }
        });
    }

    /**
     * Reorder stops in a Wave.
     */
    public function reorderStops(DeliveryWave $wave, array $stopSequences): void
    {
        DB::transaction(function () use ($wave, $stopSequences) {
            foreach ($stopSequences as $item) {
                $stopId = $item['stop_id'] ?? null;
                $sequence = (int) ($item['sequence'] ?? 1);

                if ($stopId) {
                    $stop = DeliveryStop::where('wave_id', $wave->id)->where('id', $stopId)->first();
                    if ($stop) {
                        $stop->update(['sequence' => $sequence]);
                        DeliveryWaveItem::where('wave_id', $wave->id)
                            ->where('dwo_id', $stop->dwo_id)
                            ->update(['sequence' => $sequence]);
                    }
                }
            }
        });
    }

    /**
     * Dispatch the entire wave and its contained DWOs.
     */
    public function dispatchWave(DeliveryWave $wave, User $actor): DeliveryWave
    {
        return DB::transaction(function () use ($wave, $actor) {
            if (!$wave->approved_driver_id || !$wave->vehicle_id) {
                throw ValidationException::withMessages([
                    'approved_driver_id' => ['A driver and vehicle must be assigned before dispatching a delivery wave.'],
                ]);
            }

            $wave->loadMissing(['workOrders']);

            foreach ($wave->workOrders as $dwo) {
                if ($dwo->status === DwoStatus::Loaded) {
                    $this->dwoService->transition($dwo, DwoStatus::Dispatched, $actor);
                }
            }

            $wave->status = DeliveryWaveStatus::Dispatched;
            $wave->updated_by = $actor->id;
            $wave->save();

            return $wave;
        });
    }

    /**
     * Complete a wave when all stops and DWOs are finished.
     */
    public function completeWave(DeliveryWave $wave, User $actor): DeliveryWave
    {
        return DB::transaction(function () use ($wave, $actor) {
            $wave->status = DeliveryWaveStatus::Completed;
            $wave->updated_by = $actor->id;
            $wave->save();

            return $wave;
        });
    }
}
