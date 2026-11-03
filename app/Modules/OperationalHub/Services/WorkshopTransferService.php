<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Services;

use App\Models\User;
use App\Modules\OperationalHub\Enums\SwoFulfillmentMode;
use App\Modules\OperationalHub\Enums\SwoPriority;
use App\Modules\OperationalHub\Enums\SwoStatus;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\OperationalHub\Models\SwoStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkshopTransferService
{
    /**
     * Create a downstream DRAFT Workshop Service Work Order from a Field Mission.
     *
     * Reception is intentionally NOT created here: an exit pass only marks the
     * business event "equipment left the field", while a reception marks
     * "equipment physically arrived at the workshop". They are distinct events.
     *
     * @return array{workshop_swo: ServiceWorkOrder}
     */
    public function createWorkshopSwoFromFieldMission(Mission $fieldMission, array $data, ?User $actor = null): array
    {
        $fieldSwo = $fieldMission->swo;
        if (!$fieldSwo) {
            throw ValidationException::withMessages([
                'mission_id' => ['The specified mission does not have an associated Service Work Order.'],
            ]);
        }

        $workshopUnitId = $data['destination_workshop_unit_id']
            ?? $data['workshop_unit_id']
            ?? null;

        if (!$workshopUnitId) {
            throw ValidationException::withMessages([
                'workshop_unit_id' => ['A destination workshop unit must be selected for the transfer.'],
            ]);
        }

        return DB::transaction(function () use ($fieldMission, $fieldSwo, $workshopUnitId, $data, $actor) {
            $workshopSwo = new ServiceWorkOrder();
            $workshopSwo->sales_order_id    = $fieldSwo->sales_order_id;
            $workshopSwo->fulfillment_mode  = SwoFulfillmentMode::Workshop;
            $workshopSwo->lsa_id            = $fieldSwo->lsa_id;
            $workshopSwo->client_id         = $fieldSwo->client_id;
            $workshopSwo->service_site_id   = $fieldSwo->service_site_id;
            $workshopSwo->workshop_unit_id  = $workshopUnitId;
            $workshopSwo->status            = SwoStatus::Draft;
            $workshopSwo->priority          = isset($data['priority'])
                ? (SwoPriority::tryFrom($data['priority']) ?? SwoPriority::Normal)
                : $fieldSwo->priority;
            $workshopSwo->planned_date      = $data['planned_date'] ?? null;
            $workshopSwo->source_swo_id     = $fieldSwo->id;
            $workshopSwo->source_mission_id = $fieldMission->id;
            $workshopSwo->notes             = $data['notes']
                ?? "Transferred to workshop from mission {$fieldMission->mission_number}";
            $workshopSwo->created_by        = $actor?->id;
            $workshopSwo->updated_by        = $actor?->id;
            $workshopSwo->save();

            SwoStatusHistory::create([
                'swo_id'      => $workshopSwo->id,
                'from_status' => null,
                'to_status'   => SwoStatus::Draft->value,
                'changed_by'  => $actor?->id,
                'notes'       => "Workshop SWO created from Field Mission {$fieldMission->mission_number}",
            ]);

            return [
                'workshop_swo' => $workshopSwo->fresh(['workshopUnit']),
            ];
        });
    }
}
