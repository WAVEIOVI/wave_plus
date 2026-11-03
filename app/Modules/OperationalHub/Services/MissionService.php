<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Services;

use App\Models\User;
use App\Modules\OperationalHub\Enums\MissionMode;
use App\Modules\OperationalHub\Enums\MissionStatus;
use App\Modules\OperationalHub\Enums\SwoStatus;
use App\Modules\OperationalHub\Models\ChecklistTemplate;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\MissionAttendance;
use App\Modules\OperationalHub\Models\MissionChecklist;
use App\Modules\OperationalHub\Models\MissionChecklistItem;
use App\Modules\OperationalHub\Models\MissionEmployee;
use App\Modules\OperationalHub\Models\MissionStatusHistory;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\OperationalHub\Models\WorkshopExitPass;
use App\Modules\OperationalHub\Models\WorkshopExitPassEquipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MissionService
{
    /**
     * Create a Mission under a Service Work Order.
     */
    public function createMission(ServiceWorkOrder $swo, array $data, ?User $actor = null): Mission
    {
        return DB::transaction(function () use ($swo, $data, $actor) {
            $modeVal = $data['mode'] ?? ($swo->fulfillment_mode ? $swo->fulfillment_mode->value : 'field_service');
            $mode = MissionMode::tryFrom($modeVal) ?? MissionMode::FieldService;

            $mission = new Mission();
            $mission->swo_id                  = $swo->id;
            $mission->mode                    = $mode;
            $mission->status                  = MissionStatus::Planned;
            $mission->workshop_unit_id        = $data['workshop_unit_id'] ?? $swo->workshop_unit_id;
            $mission->service_site_id         = $data['service_site_id'] ?? $swo->service_site_id;
            $mission->team_id                 = $data['team_id'] ?? $swo->team_id;
            $mission->lead_technician_id      = $data['lead_technician_id'] ?? $swo->lead_technician_id;
            $mission->vehicle_id              = $data['vehicle_id'] ?? $swo->vehicle_id;
            $mission->planned_date            = $data['planned_date'] ?? $swo->planned_date;
            $mission->planned_start           = $data['planned_start'] ?? $swo->planned_start;
            $mission->planned_end             = $data['planned_end'] ?? $swo->planned_end;
            $mission->planned_start_time      = $data['planned_start_time'] ?? $swo->planned_start_time;
            $mission->planned_duration_hours  = $data['planned_duration_hours'] ?? $swo->planned_duration_hours;
            $mission->preparation_notes       = $data['preparation_notes'] ?? null;
            $mission->client_contact_name     = $data['client_contact_name'] ?? null;
            $mission->source_field_mission_id = $data['source_field_mission_id'] ?? null;
            $mission->created_by              = $actor?->id;
            $mission->updated_by              = $actor?->id;
            $mission->save();

            // Multi-employee sync: from data or inherited from SWO
            if (!empty($data['employee_ids']) && is_array($data['employee_ids'])) {
                $this->attachEmployees($mission, $data['employee_ids'], $data['lead_technician_id'] ?? null);
            } else {
                $swo->loadMissing('swoEmployees');
                if ($swo->swoEmployees->isNotEmpty()) {
                    foreach ($swo->swoEmployees as $swoEmp) {
                        MissionEmployee::create([
                            'mission_id'  => $mission->id,
                            'employee_id' => $swoEmp->employee_id,
                            'is_lead'     => (bool) $swoEmp->is_lead,
                        ]);
                    }
                }
            }

            // Auto-instantiate checklist from the SWO (or request) template.
            $checklistTemplateId = $data['checklist_template_id'] ?? $swo->checklist_template_id;
            if ($checklistTemplateId) {
                $template = ChecklistTemplate::with('items')->find($checklistTemplateId);
                if ($template && $template->items->isNotEmpty()) {
                    $checklist = MissionChecklist::create([
                        'mission_id' => $mission->id,
                        'title'      => is_array($template->name) ? ($template->name['en'] ?? reset($template->name)) : (string) $template->name,
                        'notes'      => 'Generated from template ' . $template->code,
                        'created_by' => $actor?->id,
                    ]);

                    foreach ($template->items as $idx => $item) {
                        $title = is_array($item->title) ? ($item->title['en'] ?? reset($item->title)) : (string) $item->title;
                        MissionChecklistItem::create([
                            'checklist_id'  => $checklist->id,
                            'title'         => $title,
                            'answer'        => 'na',
                            'notes'         => null,
                            'is_completed'  => false,
                            'evidence_path' => null,
                            'sort_order'    => $item->sort_order ?? $idx,
                        ]);
                    }
                }
            }

            // Record initial history
            MissionStatusHistory::create([
                'mission_id'  => $mission->id,
                'from_status' => null,
                'to_status'   => MissionStatus::Planned->value,
                'changed_by'  => $actor?->id,
                'notes'       => 'Mission created for SWO ' . $swo->swo_number,
            ]);

            // NOTE: SWO status is NOT advanced here. The SWO lifecycle is driven by
            // SwoService::transition() to avoid circular SWO -> Mission -> SWO logic.

            return $mission;
        });
    }

    /**
     * Transition Mission status.
     */
    public function transition(Mission $mission, MissionStatus $targetStatus, ?User $actor = null, array $payload = []): Mission
    {
        $currentStatus = $mission->status;

        if ($currentStatus === $targetStatus) {
            return $mission;
        }

        if (!$currentStatus->canTransitionTo($targetStatus)) {
            throw ValidationException::withMessages([
                'status' => [
                    "Cannot transition Mission from '{$currentStatus->label()}' to '{$targetStatus->label()}'.",
                ],
            ]);
        }

        return DB::transaction(function () use ($mission, $currentStatus, $targetStatus, $actor, $payload) {
            $updates = [
                'status'     => $targetStatus,
                'updated_by' => $actor?->id,
            ];

            if (in_array($targetStatus, [MissionStatus::Started, MissionStatus::InProgress], true) && !$mission->actual_start_at) {
                $updates['actual_start_at'] = now();
            }

            if ($targetStatus === MissionStatus::Completed) {
                $updates['actual_end_at'] = now();
                if (!empty($payload['completion_notes'])) {
                    $updates['completion_notes'] = $payload['completion_notes'];
                }
                if (!empty($payload['client_signature'])) {
                    $updates['client_signature'] = $payload['client_signature'];
                }
                if (!empty($payload['client_satisfaction'])) {
                    $updates['client_satisfaction'] = $payload['client_satisfaction'];
                }
                if (!empty($payload['client_reception_at'])) {
                    $updates['client_reception_at'] = $payload['client_reception_at'];
                }
            }

            if ($targetStatus === MissionStatus::Cancelled) {
                $updates['cancellation_reason'] = $payload['cancellation_reason'] ?? $payload['notes'] ?? 'Cancelled by user';
            }

            // Mission Exit / Pause reasons are captured in history notes below.
            if ($targetStatus === MissionStatus::Paused && empty($payload['notes'])) {
                $payload['notes'] = 'Mission paused';
            }

            $mission->update($updates);

            // Record status history
            MissionStatusHistory::create([
                'mission_id'  => $mission->id,
                'from_status' => $currentStatus->value,
                'to_status'   => $targetStatus->value,
                'changed_by'  => $actor?->id,
                'notes'       => $payload['notes'] ?? null,
                'metadata'    => $payload['metadata'] ?? null,
            ]);

            // Sync with parent SWO status:
            // When mission starts -> SWO can become InProgress
            $swo = $mission->swo;
            if ($swo) {
                if (in_array($targetStatus, [MissionStatus::Started, MissionStatus::InProgress], true)) {
                    if (in_array($swo->status, [SwoStatus::Planned, SwoStatus::Scheduled], true)) {
                        if ($swo->status->canTransitionTo(SwoStatus::InProgress)) {
                            app(SwoService::class)->transition($swo, SwoStatus::InProgress, $actor, [
                                'notes' => "SWO marked In Progress following mission {$mission->mission_number} start",
                            ]);
                        }
                    }
                }
            }

            return $mission->fresh();
        });
    }

    public function startMission(Mission $m, ?User $actor = null): Mission
    {
        return $this->transition($m, MissionStatus::Started, $actor);
    }

    public function pauseMission(Mission $m, ?User $actor = null): Mission
    {
        return $this->transition($m, MissionStatus::Paused, $actor);
    }

    public function completeMission(Mission $m, array $data, ?User $actor = null): Mission
    {
        return $this->transition($m, MissionStatus::Completed, $actor, $data);
    }

    /**
     * Record attendances for mission crew.
     */
    public function recordAttendance(Mission $mission, array $attendances, ?User $actor = null): void
    {
        DB::transaction(function () use ($mission, $attendances) {
            foreach ($attendances as $row) {
                MissionAttendance::updateOrCreate(
                    [
                        'mission_id'  => $mission->id,
                        'employee_id' => $row['employee_id'],
                        'date'        => $row['date'] ?? now()->toDateString(),
                    ],
                    [
                        'arrival_time'   => $row['arrival_time'] ?? null,
                        'departure_time' => $row['departure_time'] ?? null,
                        'status'         => $row['status'] ?? 'present',
                        'notes'          => $row['notes'] ?? null,
                    ]
                );
            }
        });
    }

    /**
     * Save/sync mission checklist.
     */
    public function saveChecklist(Mission $mission, array $items, string $title = 'Pre-operation Checklist', ?User $actor = null): MissionChecklist
    {
        return DB::transaction(function () use ($mission, $items, $title, $actor) {
            $checklist = MissionChecklist::firstOrCreate(
                ['mission_id' => $mission->id, 'title' => $title],
                ['created_by' => $actor?->id]
            );

            // Sync checklist items
            $checklist->items()->delete();

            foreach ($items as $idx => $item) {
                MissionChecklistItem::create([
                    'checklist_id'  => $checklist->id,
                    'title'         => $item['title'],
                    'answer'        => $item['answer'] ?? 'na',
                    'notes'         => $item['notes'] ?? null,
                    'is_completed'  => !empty($item['is_completed']),
                    'evidence_path' => $item['evidence_path'] ?? null,
                    'sort_order'    => $idx,
                ]);
            }

            return $checklist->fresh('items');
        });
    }

    /**
     * Attach technicians to mission.
     */
    public function attachEmployees(Mission $mission, array $employeeIds, ?string $leadEmployeeId = null): void
    {
        MissionEmployee::where('mission_id', $mission->id)->delete();
        foreach (array_unique($employeeIds) as $empId) {
            if ($empId) {
                MissionEmployee::create([
                    'mission_id'  => $mission->id,
                    'employee_id' => $empId,
                    'is_lead'     => $empId === $leadEmployeeId,
                ]);
            }
        }
    }

    /**
     * Get operational summary statistics for Mission view.
     */
    public function getMissionStats(Mission $mission): array
    {
        $mission->loadMissing([
            'interventions.equipment',
            'attendances',
            'checklists.items',
            'exitPasses',
            'findings',
            'swo',
        ]);

        $interventions = $mission->interventions;
        $totalInterventions = $interventions->count();
        $completedInterventions = $interventions->where('status', 'completed')->count();
        $pendingInterventions = $totalInterventions - $completedInterventions;
        $failedInterventions = $interventions->whereIn('result', ['fail'])->count();

        // Checklists completion
        $allChecklistItems = $mission->checklists->flatMap->items;
        $totalChecklistItems = $allChecklistItems->count();
        $completedChecklistItems = $allChecklistItems->where('is_completed', true)->count();
        $checklistProgress = $totalChecklistItems > 0 ? (int) round(($completedChecklistItems / $totalChecklistItems) * 100) : 0;

        // Exit passes
        $transferStats = app(WorkshopExitPassService::class)->getTransferStats($mission);

        return [
            'mission_id'               => $mission->id,
            'mission_number'           => $mission->mission_number,
            'status'                   => $mission->status?->value,
            'mode'                     => $mission->mode?->value,
            'total_interventions'      => $totalInterventions,
            'completed_interventions'  => $completedInterventions,
            'pending_interventions'    => $pendingInterventions,
            'failed_interventions'     => $failedInterventions,
            'interventions_percentage' => $totalInterventions > 0 ? (int) round(($completedInterventions / $totalInterventions) * 100) : 0,
            'attendances_count'        => $mission->attendances->count(),
            'checklists_count'         => $mission->checklists->count(),
            'checklist_items_total'    => $totalChecklistItems,
            'checklist_items_done'     => $completedChecklistItems,
            'checklist_percentage'     => $checklistProgress,
            'transfers'                => $transferStats,
            'findings_count'           => $mission->findings->count(),
            'open_findings_count'      => $mission->findings->where('status', '!=', 'resolved')->count(),
            'documentation'            => $mission->swo
                ? app(ServiceDeliveryNoteService::class)->getDocumentableStats($mission->swo)
                : null,
        ];
    }
}
