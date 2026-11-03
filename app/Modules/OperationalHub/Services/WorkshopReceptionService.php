<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Services;

use App\Models\User;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\OperationalHub\Enums\WorkshopReceptionStatus;
use App\Modules\OperationalHub\Models\WorkshopReception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkshopReceptionService
{
    /**
     * Record a new Workshop Reception ("equipment physically arrived at workshop").
     */
    public function receiveEquipment(array $data, ?User $actor = null): WorkshopReception
    {
        $status = WorkshopReceptionStatus::tryFrom($data['status'] ?? 'received')
            ?? WorkshopReceptionStatus::Received;

        return DB::transaction(function () use ($data, $actor, $status) {
            $equipmentId = $data['equipment_id'] ?? null;

            $clientId = $data['client_id'] ?? null;
            if (!$clientId && $equipmentId) {
                $clientId = ClientEquipment::whereKey($equipmentId)->value('client_id');
            }

            if (!$clientId) {
                throw ValidationException::withMessages([
                    'client_id' => ['Unable to resolve the client for this reception.'],
                ]);
            }

            // Prevent duplicate active receptions for the same equipment.
            $duplicate = WorkshopReception::where('equipment_id', $equipmentId)
                ->whereIn(
                    'status',
                    array_map(static fn (WorkshopReceptionStatus $s) => $s->value, WorkshopReceptionStatus::activeStatuses()),
                )
                ->when(!empty($data['exit_pass_id']), fn ($q) => $q->where('exit_pass_id', $data['exit_pass_id']))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'equipment_id' => ['This equipment already has an open workshop reception.'],
                ]);
            }

            $wr = new WorkshopReception();
            $wr->workshop_unit_id     = $data['workshop_unit_id'];
            $wr->equipment_id         = $equipmentId;
            $wr->sales_order_id       = $data['sales_order_id'] ?? null;
            $wr->swo_id               = $data['swo_id'] ?? null;
            $wr->source_mission_id    = $data['source_mission_id'] ?? null;
            $wr->exit_pass_id         = $data['exit_pass_id'] ?? null;
            $wr->client_id            = $clientId;
            $wr->received_by          = $data['received_by'] ?? $actor?->id;
            $wr->reception_date       = $data['reception_date'] ?? now()->toDateString();
            $wr->condition            = $data['condition'] ?? 'intact';
            $wr->visible_damages      = $data['visible_damages'] ?? null;
            $wr->accessories_received = $data['accessories_received'] ?? null;
            $wr->notes                = $data['notes'] ?? null;
            $wr->status               = $status;
            $wr->created_by           = $actor?->id;
            $wr->updated_by           = $actor?->id;
            $wr->save();

            return $wr->fresh(['workshopUnit', 'equipment', 'client', 'receiver', 'swo', 'sourceMission', 'exitPass']);
        });
    }

    /**
     * Update reception status.
     */
    public function updateStatus(WorkshopReception $wr, WorkshopReceptionStatus $status, ?User $actor = null): WorkshopReception
    {
        $wr->update([
            'status'     => $status,
            'updated_by' => $actor?->id,
        ]);

        return $wr->fresh();
    }

    /**
     * Confirm / inspect received equipment.
     */
    public function confirmReception(WorkshopReception $wr, array $data, ?User $actor = null): WorkshopReception
    {
        $wr->update([
            'condition'            => $data['condition'] ?? $wr->condition,
            'visible_damages'      => $data['visible_damages'] ?? $wr->visible_damages,
            'accessories_received' => $data['accessories_received'] ?? $wr->accessories_received,
            'notes'                => $data['notes'] ?? $wr->notes,
            'status'               => WorkshopReceptionStatus::Received,
            'updated_by'           => $actor?->id,
        ]);

        return $wr->fresh();
    }
}
