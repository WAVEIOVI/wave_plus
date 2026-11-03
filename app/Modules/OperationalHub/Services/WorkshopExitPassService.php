<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Services;

use App\Models\User;
use App\Modules\OperationalHub\Enums\WorkshopReceptionStatus;
use App\Modules\OperationalHub\Models\Intervention;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\WorkshopExitPass;
use App\Modules\OperationalHub\Models\WorkshopExitPassEquipment;
use App\Modules\OperationalHub\Models\WorkshopReception;
use App\Traits\GeneratesReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkshopExitPassService
{
    use GeneratesReference;

    protected function getReferenceColumn(): string
    {
        return 'exit_pass_number';
    }

    protected function getDocumentSequenceName(): string
    {
        return 'EXP-' . date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    /**
     * Create a traceable Workshop Exit Pass from a Mission.
     */
    public function createExitPass(Mission $mission, array $data, ?User $actor = null): WorkshopExitPass
    {
        $swo = $mission->swo;
        if (!$swo) {
            throw ValidationException::withMessages([
                'mission_id' => ['The specified mission has no associated Service Work Order.'],
            ]);
        }

        $destinationWorkshopUnitId = $data['destination_workshop_unit_id']
            ?? $data['workshop_unit_id']
            ?? $mission->workshop_unit_id
            ?? $swo->workshop_unit_id;

        if (!$destinationWorkshopUnitId) {
            throw ValidationException::withMessages([
                'destination_workshop_unit_id' => ['A destination workshop unit must be specified.'],
            ]);
        }

        // Equipment items parsing: either array of equipment_ids or array of objects with equipment_id, condition, notes
        $items = [];
        if (!empty($data['items']) && is_array($data['items'])) {
            $items = $data['items'];
        } elseif (!empty($data['equipment_ids']) && is_array($data['equipment_ids'])) {
            foreach ($data['equipment_ids'] as $eqId) {
                $items[] = [
                    'equipment_id' => $eqId,
                    'condition'    => $data['condition'] ?? 'damaged',
                    'notes'        => $data['notes'] ?? null,
                ];
            }
        } elseif (!empty($data['equipment_id'])) {
            $items[] = [
                'equipment_id' => $data['equipment_id'],
                'condition'    => $data['condition'] ?? 'damaged',
                'notes'        => $data['notes'] ?? null,
            ];
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'equipment_ids' => ['At least one equipment item must be selected for the exit pass.'],
            ]);
        }

        $equipmentIds = array_column($items, 'equipment_id');

        // Check for duplicates in the current request
        if (count($equipmentIds) !== count(array_unique($equipmentIds))) {
            throw ValidationException::withMessages([
                'equipment_ids' => ['Duplicate equipment detected in the exit pass request.'],
            ]);
        }

        // Check for duplicate active exit passes for these equipment in this SWO/Mission
        $activePasses = WorkshopExitPass::where('swo_id', $swo->id)
            ->whereIn('status', ['pending', 'confirmed', 'received'])
            ->pluck('id');

        if ($activePasses->isNotEmpty()) {
            $alreadyAssigned = WorkshopExitPassEquipment::whereIn('exit_pass_id', $activePasses)
                ->whereIn('equipment_id', $equipmentIds)
                ->with('equipment')
                ->get();

            if ($alreadyAssigned->isNotEmpty()) {
                $codes = $alreadyAssigned->map(fn ($row) => $row->equipment?->equipment_code ?? $row->equipment_id)->join(', ');
                throw ValidationException::withMessages([
                    'equipment_ids' => [
                        "The following equipment already has an active exit pass: {$codes}",
                    ],
                ]);
            }
        }

        return DB::transaction(function () use ($mission, $swo, $destinationWorkshopUnitId, $items, $data, $actor) {
            $pass = new WorkshopExitPass();
            $pass->swo_id                       = $swo->id;
            $pass->mission_id                   = $mission->id;
            $pass->destination_workshop_unit_id = $destinationWorkshopUnitId;
            $pass->status                       = $data['status'] ?? 'pending';
            $pass->transfer_date                = $data['transfer_date'] ?? now()->toDateString();
            $pass->reason                       = $data['reason'] ?? 'Repair / Servicing in workshop';
            $pass->notes                        = $data['notes'] ?? null;
            $pass->responsible_employee_id      = $data['responsible_employee_id'] ?? $mission->lead_technician_id ?? null;
            $pass->created_by                   = $actor?->id;
            $pass->updated_by                   = $actor?->id;
            $pass->save();

            foreach ($items as $item) {
                WorkshopExitPassEquipment::create([
                    'exit_pass_id' => $pass->id,
                    'equipment_id' => $item['equipment_id'],
                    'condition'    => $item['condition'] ?? 'damaged',
                    'notes'        => $item['notes'] ?? null,
                ]);
            }

            // Auto-trigger workshop SWO if this is the first exit pass and no workshop SWO exists
            $hasWorkshopSwo = ServiceWorkOrder::where('source_swo_id', $swo->id)
                ->where('workshop_unit_id', $destinationWorkshopUnitId)
                ->exists();

            if (!$hasWorkshopSwo) {
                app(WorkshopTransferService::class)->createWorkshopSwoFromFieldMission($mission, [
                    'workshop_unit_id' => $destinationWorkshopUnitId,
                    'equipment_ids'    => array_column($items, 'equipment_id'),
                    'notes'            => "Auto-created from Exit Pass {$pass->exit_pass_number}",
                ], $actor);
            }

            return $pass->fresh(['equipment', 'destinationWorkshopUnit', 'responsibleEmployee', 'serviceWorkOrder', 'mission']);
        });
    }

    /**
     * Get equipment transfer statistics for a mission.
     */
    public function getTransferStats(Mission $mission): array
    {
        $mission->loadMissing(['interventions.equipment']);

        $missionEquipment = $mission->interventions
            ->filter(fn ($i) => !empty($i->equipment_id))
            ->map(fn ($i) => $i->equipment)
            ->unique('id')
            ->values();

        $activePassIds = WorkshopExitPass::where('mission_id', $mission->id)
            ->whereIn('status', ['pending', 'confirmed', 'received'])
            ->pluck('id');

        $transferredEquipmentIds = WorkshopExitPassEquipment::whereIn('exit_pass_id', $activePassIds)
            ->pluck('equipment_id')
            ->unique()
            ->all();

        $transferred = $missionEquipment->filter(fn ($eq) => in_array($eq->id, $transferredEquipmentIds, true))->values();
        $available = $missionEquipment->filter(fn ($eq) => !in_array($eq->id, $transferredEquipmentIds, true))->values();

        $exitPasses = WorkshopExitPass::where('mission_id', $mission->id)
            ->with(['equipment', 'destinationWorkshopUnit', 'responsibleEmployee'])
            ->orderByDesc('id')
            ->get();

        return [
            'total_mission_equipment'   => $missionEquipment->count(),
            'transferred_count'         => $transferred->count(),
            'available_count'           => $available->count(),
            'transferred_equipment'     => $transferred,
            'available_equipment'       => $available,
            'exit_passes'               => $exitPasses,
        ];
    }

    /**
     * Update exit pass status.
     */
    public function updateStatus(WorkshopExitPass $pass, string $status, ?User $actor = null): WorkshopExitPass
    {
        $validStatuses = ['pending', 'confirmed', 'received', 'returned', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => ["Invalid status '{$status}'. Valid: " . implode(', ', $validStatuses)],
            ]);
        }

        return DB::transaction(function () use ($pass, $status, $actor) {
            $pass->update([
                'status'     => $status,
                'updated_by' => $actor?->id,
            ]);

            // When received in workshop, ensure workshop receptions exist
            if ($status === 'received') {
                $pass->loadMissing(['equipment', 'serviceWorkOrder']);
                $swo = $pass->serviceWorkOrder;

                foreach ($pass->equipment as $eq) {
                    // A reception is the physical arrival of the equipment. Avoid
                    // creating duplicate reception records for the same exit pass line.
                    $exists = WorkshopReception::where('exit_pass_id', $pass->id)
                        ->where('equipment_id', $eq->id)
                        ->exists();

                    if (!$exists) {
                        WorkshopReception::create([
                            'workshop_unit_id'  => $pass->destination_workshop_unit_id,
                            'equipment_id'      => $eq->id,
                            'sales_order_id'    => $swo?->sales_order_id,
                            'swo_id'            => $swo?->id,
                            'source_mission_id' => $pass->mission_id,
                            'exit_pass_id'      => $pass->id,
                            'client_id'         => $swo?->client_id,
                            'received_by'       => $actor?->id,
                            'reception_date'    => now()->toDateString(),
                            'condition'         => $eq->pivot->condition ?? 'damaged',
                            'notes'             => $eq->pivot->notes ?? "Received via exit pass {$pass->exit_pass_number}",
                            'status'            => WorkshopReceptionStatus::Pending,
                            'created_by'        => $actor?->id,
                            'updated_by'        => $actor?->id,
                        ]);
                    }
                }
            }

            return $pass->fresh(['equipment', 'destinationWorkshopUnit', 'responsibleEmployee']);
        });
    }
}
