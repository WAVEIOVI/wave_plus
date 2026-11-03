<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Services;

use App\Models\User;
use App\Modules\OperationalHub\Models\Intervention;
use App\Modules\OperationalHub\Models\ServiceDeliveryNote;
use App\Modules\OperationalHub\Models\ServiceDeliveryNoteLine;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceDeliveryNoteService
{
    /**
     * Generate a Service Delivery Note (SDN) from an SWO with DELTA logic.
     * Only includes undocumented completed interventions (or optionally for a specific mission).
     */
    public function generateFromSwo(ServiceWorkOrder $swo, ?User $actor = null, ?string $missionId = null, ?array $options = []): ServiceDeliveryNote
    {
        return DB::transaction(function () use ($swo, $actor, $missionId, $options) {
            $sdn = new ServiceDeliveryNote;
            $sdn->swo_id = $swo->id;
            $sdn->mission_id = $missionId;
            $sdn->client_id = $swo->client_id;
            $sdn->service_site_id = $options['service_site_id'] ?? $swo->service_site_id;
            $sdn->issued_date = $options['issued_date'] ?? now()->toDateString();
            $sdn->period_start = $options['period_start'] ?? null;
            $sdn->period_end = $options['period_end'] ?? null;
            $sdn->issued_by = $actor?->id ?? $swo->client_id;
            $sdn->status = 'issued';
            $sdn->notes = $options['notes'] ?? null;
            $sdn->created_by = $actor?->id;
            $sdn->updated_by = $actor?->id;
            $sdn->save();

            // Find all intervention IDs already documented in active SDNs for this SWO
            $alreadyDocumentedInvIds = ServiceDeliveryNoteLine::whereHas('deliveryNote', function ($q) use ($swo) {
                $q->where('swo_id', $swo->id)->where('status', '!=', 'cancelled');
            })->whereNotNull('intervention_id')->pluck('intervention_id')->toArray();

            // Source of truth: COMPLETED, undocumented interventions (delta only).
            $query = Intervention::whereHas('mission', function ($q) use ($swo, $missionId) {
                $q->where('swo_id', $swo->id);
                if ($missionId) {
                    $q->where('id', $missionId);
                }
            })
                ->where('status', 'completed')
                ->whereNotIn('id', $alreadyDocumentedInvIds)
                ->with(['equipment', 'blueprint']);

            $interventions = $query->get();

            if ($interventions->isEmpty()) {
                throw ValidationException::withMessages([
                    'interventions' => ['No newly completed, undocumented interventions are available for this Service Delivery Note.'],
                ]);
            }

            foreach ($interventions as $inv) {
                $equipmentName = $inv->equipment?->equipment_code
                    ?? ($inv->blueprint?->getTranslations('name')['en'] ?? 'Service intervention');
                ServiceDeliveryNoteLine::create([
                    'sdn_id' => $sdn->id,
                    'intervention_id' => $inv->id,
                    'equipment_id' => $inv->equipment_id,
                    'description' => "{$inv->service_type} on {$equipmentName}: ".($inv->result ?? 'completed'),
                    'service_type' => $inv->service_type,
                    'result' => $inv->result ?? 'completed',
                    'technician_name' => $actor?->name ?? 'Technician',
                    'documented_at' => now(),
                ]);
            }

            return $sdn->fresh(['lines.equipment', 'client', 'serviceSite', 'issuer', 'mission']);
        });
    }

    /**
     * Get documentable statistics for an SWO (delta tracking).
     */
    public function getDocumentableStats(ServiceWorkOrder $swo): array
    {
        $allInterventions = Intervention::whereHas('mission', function ($q) use ($swo) {
            $q->where('swo_id', $swo->id);
        })->get();

        // Only completed work counts towards documentation.
        $completedIds = $allInterventions->where('status', 'completed')->pluck('id')->all();

        $alreadyDocumentedInvIds = ServiceDeliveryNoteLine::whereHas('deliveryNote', function ($q) use ($swo) {
            $q->where('swo_id', $swo->id)->where('status', '!=', 'cancelled');
        })->whereNotNull('intervention_id')->pluck('intervention_id')->unique()->all();

        $documentedCount = count(array_intersect($completedIds, $alreadyDocumentedInvIds));
        $availableForDocumentation = max(0, count($completedIds) - $documentedCount);

        return [
            'total_interventions' => $allInterventions->count(),
            'completed_count' => count($completedIds),
            'documented_count' => $documentedCount,
            'available_for_documentation' => $availableForDocumentation,
            'undocumented_count' => $availableForDocumentation,
            // Aliases kept for the mission execution UI contract.
            'documentable_interventions_count' => $availableForDocumentation,
            'already_documented_count' => $documentedCount,
            'has_undocumented' => $availableForDocumentation > 0,
            'sdn_count' => ServiceDeliveryNote::where('swo_id', $swo->id)->count(),
        ];
    }

    /**
     * Sign the Service Delivery Note.
     */
    public function sign(ServiceDeliveryNote $sdn, array $data, ?User $actor = null): ServiceDeliveryNote
    {
        $sdn->update([
            'signed_at' => now(),
            'client_signer_name' => $data['client_signer_name'] ?? 'Client Representative',
            'client_signature' => $data['client_signature'] ?? null,
            'status' => 'signed',
            'notes' => $data['notes'] ?? $sdn->notes,
            'updated_by' => $actor?->id,
        ]);

        return $sdn->fresh();
    }
}
