<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Services;

use App\Models\User;
use App\Modules\Catalog\Models\BlueprintInterventionCycle;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\ClientAssets\Models\ClientEquipmentTransfer;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\ClientAssets\Models\ClientSiteArea;
use App\Modules\ClientAssets\Models\EquipmentInterventionCycle;
use App\Modules\ClientAssets\Models\EquipmentLocationHistory;
use App\Modules\ClientAssets\Models\EquipmentOptionValue;
use App\Modules\ClientAssets\Models\EquipmentQrCode;
use App\Modules\ClientAssets\Models\EquipmentStatusHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EquipmentManagementService
{
    /**
     * Atomically create a new ClientEquipment along with all snapshot records:
     * - Options values snapshots
     * - Intervention cycles snapshots (from blueprint or custom)
     * - Initial QR code history
     * - Initial location history (if deployed to site/area)
     * - Initial status history
     */
    public function createEquipment(array $data, ?User $user = null): ClientEquipment
    {
        $this->validateLocationHierarchy($data);

        return DB::transaction(function () use ($data, $user) {
            $userId = $user?->id;
            $now = now();

            // Extract nested snapshots data if provided
            $optionsInput = $data['options'] ?? [];
            $cyclesInput = $data['intervention_cycles'] ?? [];
            $qrCodeInput = $data['qr_code'] ?? null;
            unset($data['options'], $data['intervention_cycles'], $data['qr_code']);

            // Set creator
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;

            // Generate dedicated technical UUID
            $data['uuid'] = (string) Str::uuid();

            /** @var ClientEquipment $equipment */
            $equipment = ClientEquipment::create($data);

            // ── 1. Create Initial QR Code Record ──────────────────────────
            $finalQrCode = ! empty($qrCodeInput) ? $qrCodeInput : $equipment->equipment_code;
            EquipmentQrCode::create([
                'equipment_id' => $equipment->id,
                'qr_code' => $finalQrCode,
                'is_current' => true,
                'assigned_at' => $now,
                'reason' => 'Initial assignment upon equipment registration',
                'created_by' => $userId,
            ]);

            // ── 2. Create Initial Status History Record ────────────────────
            EquipmentStatusHistory::create([
                'equipment_id' => $equipment->id,
                'status' => $equipment->lifecycle_status ?: 'operational',
                'started_at' => $now,
                'reason' => 'Initial asset registration',
                'changed_by' => $userId,
            ]);

            // ── 3. Create Initial Location History (if site specified) ─────
            if ($equipment->site_id) {
                EquipmentLocationHistory::create([
                    'equipment_id' => $equipment->id,
                    'client_id' => $equipment->client_id,
                    'site_id' => $equipment->site_id,
                    'area_id' => $equipment->area_id,
                    'started_at' => $now,
                    'movement_type' => 'initial_placement',
                    'reason' => 'Initial commissioning at client site',
                    'changed_by' => $userId,
                ]);
            }

            // ── 4. Snapshot Blueprint Options ─────────────────────────────
            $this->saveOptionsSnapshot($equipment, $optionsInput);

            // ── 5. Snapshot Blueprint Intervention Cycles ─────────────────
            $this->saveInterventionCyclesSnapshot($equipment, $cyclesInput);

            return $equipment;
        });
    }

    /**
     * Atomically update equipment details and track status/location transitions.
     */
    public function updateEquipment(ClientEquipment $equipment, array $data, ?User $user = null): ClientEquipment
    {
        $this->validateLocationHierarchy($data, $equipment);

        return DB::transaction(function () use ($equipment, $data, $user) {
            $userId = $user?->id;
            $now = now();

            $optionsInput = $data['options'] ?? null;
            $cyclesInput = $data['intervention_cycles'] ?? null;
            $statusChangeReason = $data['status_change_reason'] ?? null;
            unset($data['options'], $data['intervention_cycles'], $data['status_change_reason']);

            $data['updated_by'] = $userId;

            $equipment = ClientEquipment::query()->lockForUpdate()->findOrFail($equipment->id);

            if (array_key_exists('client_id', $data) && $data['client_id'] !== $equipment->client_id) {
                throw ValidationException::withMessages([
                    'client_id' => 'Cannot reassign equipment to another client. Keep the asset on the same client or register a new one.',
                ]);
            }

            // Track lifecycle status changes ONLY if actually changed
            $newStatus = $data['lifecycle_status'] ?? null;
            if ($newStatus && $newStatus !== $equipment->lifecycle_status) {
                // Close previous status history
                EquipmentStatusHistory::where('equipment_id', $equipment->id)
                    ->whereNull('ended_at')
                    ->update(['ended_at' => $now]);

                // Create new status history
                EquipmentStatusHistory::create([
                    'equipment_id' => $equipment->id,
                    'status' => $newStatus,
                    'started_at' => $now,
                    'reason' => $statusChangeReason ?: 'Status updated via asset management',
                    'changed_by' => $userId,
                ]);
            }

            // If location changed directly via update, update location history ONLY if actually changed
            $siteChanged = array_key_exists('site_id', $data) && $data['site_id'] !== $equipment->site_id;
            $areaChanged = array_key_exists('area_id', $data) && $data['area_id'] !== $equipment->area_id;

            if ($siteChanged || $areaChanged) {
                $targetSiteId = array_key_exists('site_id', $data) ? $data['site_id'] : $equipment->site_id;
                $targetAreaId = array_key_exists('area_id', $data) ? $data['area_id'] : $equipment->area_id;
                $movementType = $targetSiteId ? 'relocation' : 'removal_to_storage';

                $this->recordLocationMove(
                    $equipment,
                    $targetSiteId,
                    $targetAreaId,
                    $userId,
                    $movementType,
                    'Location updated via asset edit',
                    null,
                    null,
                    $now,
                );
            }

            $equipment->update($data);

            if ($optionsInput !== null) {
                $equipment->optionValues()->delete();
                $this->saveOptionsSnapshot($equipment, $optionsInput);
            }

            if ($cyclesInput !== null) {
                $equipment->interventionCycles()->delete();
                $this->saveInterventionCyclesSnapshot($equipment, $cyclesInput);
            }

            return $equipment;
        });
    }

    /**
     * Explicit Transfer Operation:
     * Atomically moves equipment from one location to another (or unassigns it).
     */
    public function transferEquipment(ClientEquipment $equipment, array $data, ?User $user = null): ClientEquipment
    {
        $targetSiteId = $data['to_site_id'] ?? null;
        $targetAreaId = $data['to_area_id'] ?? null;
        $movementType = $data['movement_type'] ?? ($targetSiteId ? 'transfer' : 'removal_to_storage');
        $reason = $data['reason'] ?? 'Equipment transfer';
        $notes = $data['notes'] ?? null;
        $conditionAtTransfer = $data['condition_at_transfer'] ?? null;

        // Validation: if target site provided, verify it belongs to equipment client
        if ($targetSiteId) {
            $site = ClientSite::findOrFail($targetSiteId);
            if ($site->client_id !== $equipment->client_id) {
                throw ValidationException::withMessages([
                    'to_site_id' => 'Target site does not belong to the equipment\'s client.',
                ]);
            }

            if ($targetAreaId) {
                $area = ClientSiteArea::findOrFail($targetAreaId);
                if ($area->site_id !== $targetSiteId) {
                    throw ValidationException::withMessages([
                        'to_area_id' => 'Target area does not belong to the target site.',
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($equipment, $targetSiteId, $targetAreaId, $movementType, $reason, $notes, $conditionAtTransfer, $user) {
            $userId = $user?->id;
            $now = now();
            $equipment = ClientEquipment::query()->lockForUpdate()->findOrFail($equipment->id);

            $this->recordLocationMove(
                $equipment,
                $targetSiteId,
                $targetAreaId,
                $userId,
                $movementType,
                $reason,
                $notes,
                $conditionAtTransfer,
                $now,
            );

            $updateData = [
                'site_id' => $targetSiteId,
                'area_id' => $targetAreaId,
                'updated_by' => $userId,
            ];
            if ($conditionAtTransfer) {
                $updateData['condition_status'] = $conditionAtTransfer;
            }

            $equipment->update($updateData);

            return $equipment;
        });
    }

    /**
     * Atomically replace QR code:
     * - Deactivates all previous active QR codes (unassigned_at = now).
     * - Inserts new QR code with is_current = true.
     * - Preserves technical equipment uuid intact.
     */
    public function replaceQrCode(ClientEquipment $equipment, array $data, ?User $user = null): EquipmentQrCode
    {
        $newQrCode = $data['new_qr_code'] ?? (string) Str::uuid();
        $reason = $data['reason'] ?? 'Damaged or replaced physical tag';
        $notes = $data['notes'] ?? null;

        // Ensure unique
        if (EquipmentQrCode::where('qr_code', $newQrCode)->exists()) {
            throw ValidationException::withMessages([
                'new_qr_code' => 'The specified QR code is already assigned to an asset.',
            ]);
        }

        return DB::transaction(function () use ($equipment, $newQrCode, $reason, $notes, $user) {
            $userId = $user?->id;
            $now = now();
            $equipment = ClientEquipment::query()->lockForUpdate()->findOrFail($equipment->id);

            // Deactivate all existing current QR codes
            EquipmentQrCode::where('equipment_id', $equipment->id)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'unassigned_at' => $now,
                ]);

            // Create single new active QR code
            $qrRecord = EquipmentQrCode::create([
                'equipment_id' => $equipment->id,
                'qr_code' => $newQrCode,
                'is_current' => true,
                'assigned_at' => $now,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $equipment->update(['updated_by' => $userId]);

            return $qrRecord;
        });
    }

    private function recordLocationMove(
        ClientEquipment $equipment,
        ?string $targetSiteId,
        ?string $targetAreaId,
        ?string $userId,
        string $movementType,
        ?string $reason,
        ?string $notes,
        ?string $conditionAtTransfer,
        $now,
    ): void {
        EquipmentLocationHistory::where('equipment_id', $equipment->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => $now]);

        if ($targetSiteId) {
            EquipmentLocationHistory::create([
                'equipment_id' => $equipment->id,
                'client_id' => $equipment->client_id,
                'site_id' => $targetSiteId,
                'area_id' => $targetAreaId,
                'started_at' => $now,
                'movement_type' => $movementType,
                'reason' => $reason,
                'notes' => $notes,
                'changed_by' => $userId,
            ]);
        }

        ClientEquipmentTransfer::create([
            'equipment_id' => $equipment->id,
            'from_site_id' => $equipment->site_id,
            'from_area_id' => $equipment->area_id,
            'to_site_id' => $targetSiteId,
            'to_area_id' => $targetAreaId,
            'transferred_by' => $userId,
            'transfer_date' => $now,
            'reason' => $reason,
            'condition_at_transfer' => $conditionAtTransfer,
            'notes' => $notes,
        ]);
    }

    /**
     * Save option snapshots from Blueprint or custom input.
     */
    private function saveOptionsSnapshot(ClientEquipment $equipment, array $optionsInput): void
    {
        if (empty($optionsInput)) {
            return;
        }

        foreach ($optionsInput as $opt) {
            if (empty($opt['blueprint_option_id'])) {
                continue;
            }

            EquipmentOptionValue::create([
                'equipment_id' => $equipment->id,
                'blueprint_option_id' => $opt['blueprint_option_id'],
                'option_value_id' => $opt['option_value_id'] ?? null,
                'option_name' => $opt['option_name'] ?? null,
                'value_text' => $opt['value_text'] ?? null,
                'value_number' => $opt['value_number'] ?? null,
                'value_boolean' => $opt['value_boolean'] ?? null,
                'value_date' => $opt['value_date'] ?? null,
            ]);
        }
    }

    /**
     * Save intervention cycles snapshot from Blueprint defaults or input.
     */
    private function saveInterventionCyclesSnapshot(ClientEquipment $equipment, array $cyclesInput): void
    {
        if (empty($cyclesInput) && $equipment->blueprint_id) {
            $bpCycles = BlueprintInterventionCycle::where('blueprint_id', $equipment->blueprint_id)->get();
            $baseDate = $equipment->install_date ? Carbon::parse($equipment->install_date) : now();

            foreach ($bpCycles as $bpCycle) {
                $months = $bpCycle->recurring_interval_months ?: ($bpCycle->first_interval_months ?: 12);
                $nextDue = (clone $baseDate)->addMonths($months)->toDateString();

                EquipmentInterventionCycle::create([
                    'equipment_id' => $equipment->id,
                    'source_blueprint_cycle_id' => $bpCycle->id,
                    'cycle_type' => $bpCycle->intervention_type,
                    'interval_value' => $months,
                    'interval_unit' => 'months',
                    'last_intervention_date' => $equipment->install_date,
                    'next_due_date' => $nextDue,
                    'enabled' => true,
                    'notes' => $bpCycle->notes,
                ]);
            }

            return;
        }

        foreach ($cyclesInput as $cycle) {
            if (empty($cycle['cycle_type'])) {
                continue;
            }

            EquipmentInterventionCycle::create([
                'equipment_id' => $equipment->id,
                'source_blueprint_cycle_id' => $cycle['source_blueprint_cycle_id'] ?? null,
                'cycle_type' => $cycle['cycle_type'],
                'interval_value' => $cycle['interval_value'] ?? 12,
                'interval_unit' => $cycle['interval_unit'] ?? 'months',
                'last_intervention_date' => $cycle['last_intervention_date'] ?? null,
                'next_due_date' => $cycle['next_due_date'] ?? null,
                'enabled' => $cycle['enabled'] ?? true,
                'notes' => $cycle['notes'] ?? null,
            ]);
        }
    }

    /**
     * Validate location hierarchy rules:
     * - Site must belong to Client
     * - Area must belong to Site
     */
    private function validateLocationHierarchy(array $data, ?ClientEquipment $existing = null): void
    {
        $clientId = $data['client_id'] ?? $existing?->client_id;
        $siteId = array_key_exists('site_id', $data) ? $data['site_id'] : $existing?->site_id;
        $areaId = array_key_exists('area_id', $data) ? $data['area_id'] : $existing?->area_id;

        if ($siteId && $clientId) {
            $site = ClientSite::find($siteId);
            if ($site && $site->client_id !== $clientId) {
                throw ValidationException::withMessages([
                    'site_id' => 'The selected Site does not belong to the specified Client.',
                ]);
            }
        }

        if ($areaId && $siteId) {
            $area = ClientSiteArea::find($areaId);
            if ($area && $area->site_id !== $siteId) {
                throw ValidationException::withMessages([
                    'area_id' => 'The selected Area does not belong to the specified Site.',
                ]);
            }
        } elseif ($areaId && empty($siteId)) {
            throw ValidationException::withMessages([
                'area_id' => 'An Area cannot be assigned without selecting a Site.',
            ]);
        }
    }
}
