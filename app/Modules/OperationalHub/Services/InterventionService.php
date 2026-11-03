<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Services;

use App\Models\User;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\OperationalHub\Enums\FindingType;
use App\Modules\OperationalHub\Models\Finding;
use App\Modules\OperationalHub\Models\Intervention;
use App\Modules\OperationalHub\Models\InterventionMaterial;
use App\Modules\OperationalHub\Models\Mission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InterventionService
{
    /**
     * Create an intervention on a mission for a specific client equipment.
     */
    public function createIntervention(Mission $mission, array $data, ?User $actor = null): Intervention
    {
        return DB::transaction(function () use ($mission, $data, $actor) {
            $equipmentId = $data['equipment_id'] ?? null;
            $blueprintId = $data['blueprint_id'] ?? null;

            if ($equipmentId && !$blueprintId) {
                $eq = ClientEquipment::find($equipmentId);
                if ($eq) {
                    $blueprintId = $eq->blueprint_id;
                }
            }

            // One intervention per equipment per execution context (mission).
            // Reuse an existing open intervention instead of silently duplicating it.
            if ($equipmentId) {
                $existing = Intervention::where('mission_id', $mission->id)
                    ->where('equipment_id', $equipmentId)
                    ->where('status', '!=', 'completed')
                    ->latest('id')
                    ->first();

                if ($existing) {
                    return $existing->loadMissing(['materials', 'findings', 'equipment', 'blueprint']);
                }
            }

            $intervention = new Intervention();
            $intervention->mission_id             = $mission->id;
            $intervention->equipment_id           = $equipmentId;
            $intervention->blueprint_id           = $blueprintId;
            $intervention->service_type           = $data['service_type'] ?? 'maintenance';
            $intervention->status                 = $data['status'] ?? 'pending';
            $intervention->result                 = $data['result'] ?? null;
            $intervention->technician_notes       = $data['technician_notes'] ?? null;
            $intervention->technical_observations = $data['technical_observations'] ?? null;
            $intervention->measurements           = $data['measurements'] ?? null;
            $intervention->started_at             = !empty($data['started_at']) ? $data['started_at'] : null;
            $intervention->completed_at           = !empty($data['completed_at']) ? $data['completed_at'] : null;
            $intervention->created_by             = $actor?->id;
            $intervention->updated_by             = $actor?->id;
            $intervention->save();

            // Add materials if provided
            if (!empty($data['materials'])) {
                foreach ($data['materials'] as $mat) {
                    $this->addMaterial($intervention, $mat, $actor);
                }
            }

            // Add findings if provided
            if (!empty($data['findings'])) {
                foreach ($data['findings'] as $f) {
                    $this->addFinding($intervention, $f, $actor);
                }
            }

            return $intervention->fresh(['materials', 'findings', 'equipment', 'blueprint']);
        });
    }

    /**
     * Update an intervention.
     */
    public function updateIntervention(Intervention $intervention, array $data, ?User $actor = null): Intervention
    {
        return DB::transaction(function () use ($intervention, $data, $actor) {
            $fields = [
                'service_type'           => $data['service_type'] ?? $intervention->service_type,
                'status'                 => $data['status'] ?? $intervention->status,
                'result'                 => $data['result'] ?? $intervention->result,
                'technician_notes'       => $data['technician_notes'] ?? $intervention->technician_notes,
                'technical_observations' => $data['technical_observations'] ?? $intervention->technical_observations,
                'measurements'           => $data['measurements'] ?? $intervention->measurements,
                'updated_by'             => $actor?->id,
            ];

            if (!empty($data['started_at'])) {
                $fields['started_at'] = $data['started_at'];
            }
            if (!empty($data['completed_at'])) {
                $fields['completed_at'] = $data['completed_at'];
            }

            $intervention->update($fields);

            return $intervention->fresh(['materials', 'findings', 'equipment', 'blueprint']);
        });
    }

    /**
     * Complete an intervention.
     */
    public function completeIntervention(Intervention $intervention, array $data, ?User $actor = null): Intervention
    {
        $data['status'] = 'completed';
        $data['completed_at'] = now();
        return $this->updateIntervention($intervention, $data, $actor);
    }

    /**
     * Add material used to an intervention.
     */
    public function addMaterial(Intervention $intervention, array $data, ?User $actor = null): InterventionMaterial
    {
        $item = CatalogItem::find($data['catalog_item_id']);

        // Catalog variant resolution: auto-select a single variant, otherwise require an
        // explicit choice. Never silently pick an arbitrary variant.
        $variant = null;
        if (!empty($data['catalog_variant_id'])) {
            $variant = CatalogVariant::whereKey($data['catalog_variant_id'])
                ->where('catalog_item_id', $data['catalog_item_id'])
                ->first();

            if (!$variant) {
                throw ValidationException::withMessages([
                    'catalog_variant_id' => ['The selected variant does not belong to the chosen catalog item.'],
                ]);
            }
        } elseif ($item) {
            $variants = $item->variants()->get();
            if ($variants->count() === 1) {
                $variant = $variants->first();
            } elseif ($variants->count() > 1) {
                throw ValidationException::withMessages([
                    'catalog_variant_id' => ['This catalog item has multiple variants. Please select one.'],
                ]);
            }
        }

        return InterventionMaterial::create([
            'intervention_id'      => $intervention->id,
            'catalog_item_id'      => $data['catalog_item_id'],
            'catalog_variant_id'   => $variant?->id,
            'description_snapshot' => $data['description_snapshot'] ?? $item?->name ?? 'Material',
            'qty_used'             => $data['qty_used'] ?? 1,
            'uom_code_snapshot'    => $data['uom_code_snapshot'] ?? $item?->uom?->code ?? 'EA',
            'variant_sku_snapshot' => $data['variant_sku_snapshot'] ?? $variant?->sku ?? null,
            'notes'                => $data['notes'] ?? null,
            'created_by'           => $actor?->id,
        ]);
    }

    /**
     * Add a finding (conformity, non-conformity, observation, recommendation) to an intervention or mission.
     */
    public function addFinding(Model $entity, array $data, ?User $actor = null): Finding
    {
        $findingType = isset($data['type']) ? FindingType::tryFrom($data['type']) ?? FindingType::Observation : FindingType::Observation;

        return Finding::create([
            'findable_type'              => $entity->getMorphClass(),
            'findable_id'                => $entity->getKey(),
            'type'                       => $findingType,
            'title'                      => $data['title'] ?? 'Finding',
            'description'                => $data['description'] ?? null,
            'severity'                   => $data['severity'] ?? 'low',
            'recommendation'             => $data['recommendation'] ?? null,
            'requires_workshop_transfer' => !empty($data['requires_workshop_transfer']),
            'status'                     => $data['status'] ?? 'open',
            'created_by'                 => $actor?->id,
            'updated_by'                 => $actor?->id,
        ]);
    }
}
