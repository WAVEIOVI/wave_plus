<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Services;

use App\Models\User;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Services\SalesOrderService;
use App\Modules\OperationalHub\Enums\MissionMode;
use App\Modules\OperationalHub\Enums\SwoFulfillmentMode;
use App\Modules\OperationalHub\Enums\SwoPriority;
use App\Modules\OperationalHub\Enums\SwoStatus;
use App\Modules\OperationalHub\Models\Intervention;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\OperationalHub\Models\SwoEmployee;
use App\Modules\OperationalHub\Models\SwoItem;
use App\Modules\OperationalHub\Models\SwoLocation;
use App\Modules\OperationalHub\Models\SwoStatusHistory;
use App\Modules\OperationalHub\Models\WorkshopExitPass;
use App\Modules\OperationalHub\Models\WorkshopExitPassEquipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SwoService
{
    /**
     * Inspect a Sales Order and calculate allocatable quantities per service line.
     */
    public function getEligibleSoLines(SalesOrder $so): array
    {
        $so->loadMissing(['lines.catalogItem', 'lines.unit']);

        $results = [];

        foreach ($so->lines as $line) {
            $snapshotVal = $line->item_type_snapshot instanceof \BackedEnum
                ? $line->item_type_snapshot->value
                : (string) ($line->item_type_snapshot ?? '');

            // Only service lines are eligible for SWOs
            if ($snapshotVal !== 'service' && $line->catalogItem?->type !== 'service') {
                continue;
            }

            $alreadyAllocated = (float) SwoItem::where('so_line_id', $line->id)
                ->whereHas('serviceWorkOrder', function ($q) {
                    $q->where('status', '!=', SwoStatus::Cancelled->value);
                })
                ->sum('qty_ordered');

            $lineQty = (float) $line->qty;
            $remaining = max(0.0, round($lineQty - $alreadyAllocated, 3));

            $results[] = [
                'so_line_id' => $line->id,
                'catalog_item_id' => $line->catalog_item_id,
                'description' => $line->description_snapshot ?? $line->catalogItem?->name ?? 'Service line',
                'uom_code' => $line->unit?->code ?? $line->catalogItem?->uom?->code ?? 'EA',
                'qty_ordered' => $lineQty,
                'qty_already_allocated' => $alreadyAllocated,
                'qty_remaining' => $remaining,
                'is_fully_allocated' => $remaining <= 0.0001,
            ];
        }

        return $results;
    }

    /**
     * Create a Service Work Order from an Accepted Sales Order.
     */
    public function createFromSalesOrder(SalesOrder $so, array $data, ?User $actor = null): ServiceWorkOrder
    {
        $soStatusVal = $so->status instanceof \BackedEnum ? $so->status->value : (string) $so->status;

        if (! in_array($soStatusVal, ['accepted'], true)) {
            throw ValidationException::withMessages([
                'sales_order_id' => [
                    "Service Work Orders can only be created for Accepted Sales Orders. Current status is: {$soStatusVal}",
                ],
            ]);
        }

        $soTypeVal = $so->so_type instanceof \BackedEnum ? $so->so_type->value : (string) $so->so_type;
        if ($soTypeVal !== 'service') {
            throw ValidationException::withMessages([
                'sales_order_id' => [
                    'Service Work Orders can only be created from Service Sales Orders. Goods SOs route to Logistics.',
                ],
            ]);
        }

        // Determine fulfillment mode (inherit from SO or request, fallback to field_service)
        $fulfillmentModeVal = $data['fulfillment_mode'] ?? null;
        if (! $fulfillmentModeVal) {
            $fulfillmentModeVal = $so->requested_fulfillment_mode instanceof \BackedEnum
                ? $so->requested_fulfillment_mode->value
                : (string) ($so->requested_fulfillment_mode ?? 'field_service');
        }

        if ($fulfillmentModeVal === 'mobile_workshop') {
            $fulfillmentModeVal = SwoFulfillmentMode::FieldService->value;
        }

        $fulfillmentMode = SwoFulfillmentMode::tryFrom($fulfillmentModeVal) ?? SwoFulfillmentMode::FieldService;

        return DB::transaction(function () use ($so, $data, $actor, $fulfillmentMode) {
            // Validate Service SO type
            if ($so->so_type !== \App\Modules\Crm\Enums\SalesOrderType::Service) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sales_order_id' => 'SWO can only be created from Service-type Sales Orders.',
                ]);
            }

            // Validate fulfillment mode for Service SO
            if (! \App\Modules\Crm\Enums\SoFulfillmentMode::isValidForSoType($fulfillmentMode->value, $so->so_type->value)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'fulfillment_mode' => "Fulfillment mode '{$fulfillmentMode->value}' is not valid for Service Sales Orders.",
                ]);
            }

            // Check available quantities on SO lines (prevent over-allocation across SWOs)
            $this->validateSoLineQuantities($so, $data['lines'] ?? []);

            $swo = new ServiceWorkOrder;
            $swo->sales_order_id = $so->id;
            $swo->fulfillment_mode = $fulfillmentMode;
            $swo->lsa_id = $so->lsa_id;
            $swo->client_id = $so->client_id;
            // Derive service_site_id from SO locations (primary service location) or explicit data
            $serviceSiteId = $data['service_site_id'] ?? null;
            if (! $serviceSiteId) {
                $so->loadMissing('locations');
                $primaryLocation = $so->locations->where('purpose', 'service')->where('is_primary', true)->first();
                if (! $primaryLocation) {
                    $primaryLocation = $so->locations->where('purpose', 'service')->first();
                }
                $serviceSiteId = $primaryLocation?->client_site_id;
            }
            $swo->service_site_id = $serviceSiteId;
            $swo->team_id = $data['team_id'] ?? null;
            $swo->lead_technician_id = $data['lead_technician_id'] ?? null;
            $swo->workshop_unit_id = $data['workshop_unit_id'] ?? $so->workshop_unit_id ?? null;
            $swo->vehicle_id = $data['vehicle_id'] ?? null;
            $swo->approved_driver_id = $data['approved_driver_id'] ?? null;
            $swo->mobile_warehouse_id = $data['mobile_warehouse_id'] ?? null;
            $swo->status = SwoStatus::Draft;
            $swo->priority = isset($data['priority']) ? SwoPriority::tryFrom($data['priority']) ?? SwoPriority::Normal : SwoPriority::Normal;
            $swo->scheduled_date = $data['scheduled_date'] ?? $data['planned_date'] ?? null;
            $swo->planned_date = $data['planned_date'] ?? $so->committed_delivery_date_from ?? null;
            $swo->planned_start = $data['planned_start'] ?? ($so->committed_delivery_date_from ? $so->committed_delivery_date_from.' 08:00:00' : null);
            $swo->planned_end = $data['planned_end'] ?? ($so->committed_delivery_date_to ? $so->committed_delivery_date_to.' 17:00:00' : null);
            $swo->planned_start_time = $data['planned_start_time'] ?? null;
            $swo->planned_duration_hours = $data['planned_duration_hours'] ?? null;
            $swo->checklist_template_id = $data['checklist_template_id'] ?? null;
            $swo->notes = $data['notes'] ?? null;
            $swo->source_swo_id = $data['source_swo_id'] ?? null;
            $swo->source_mission_id = $data['source_mission_id'] ?? null;
            $swo->created_by = $actor?->id;
            $swo->updated_by = $actor?->id;
            $swo->save();

            // Attach locations (multi-site support)
            $siteIds = [];
            if (! empty($data['site_ids']) && is_array($data['site_ids'])) {
                $siteIds = $data['site_ids'];
            } elseif (! empty($data['locations']) && is_array($data['locations'])) {
                $siteIds = array_column($data['locations'], 'client_site_id');
            } else {
                // Default: import all sites from SO locations pivot or service_site_id
                $so->loadMissing('locations');
                if ($so->locations->isNotEmpty()) {
                    $siteIds = $so->locations->pluck('client_site_id')->unique()->toArray();
                } elseif ($swo->service_site_id) {
                    $siteIds = [$swo->service_site_id];
                }
            }

            if (! empty($siteIds)) {
                $this->attachLocations($swo, $siteIds);
            }

            // Attach multi-employee technicians if provided
            if (! empty($data['employee_ids']) && is_array($data['employee_ids'])) {
                $this->attachEmployees($swo, $data['employee_ids'], $data['lead_technician_id'] ?? null);
            } elseif (! empty($data['employees']) && is_array($data['employees'])) {
                $empList = [];
                $leadId = null;
                foreach ($data['employees'] as $emp) {
                    $empId = is_array($emp) ? ($emp['id'] ?? $emp['employee_id']) : $emp;
                    $empList[] = $empId;
                    if (is_array($emp) && ! empty($emp['is_lead'])) {
                        $leadId = $empId;
                    }
                }
                $this->attachEmployees($swo, $empList, $leadId ?? $data['lead_technician_id'] ?? null);
            }

            // Populate lines (display/informational lines)
            $eligibleLines = $this->getEligibleSoLines($so);
            $eligibleMap = collect($eligibleLines)->keyBy('so_line_id');

            $requestedLines = $data['lines'] ?? [];
            if (empty($requestedLines)) {
                foreach ($eligibleLines as $el) {
                    $swo->items()->create([
                        'so_line_id' => $el['so_line_id'],
                        'catalog_item_id' => $el['catalog_item_id'],
                        'qty_ordered' => $el['qty_ordered'],
                        'qty_completed' => 0,
                        'status' => 'pending',
                        'created_by' => $actor?->id,
                    ]);
                }
            } else {
                foreach ($requestedLines as $reqLine) {
                    $soLineId = $reqLine['so_line_id'];
                    $el = $eligibleMap->get($soLineId);
                    $qty = (float) ($reqLine['qty'] ?? ($el ? $el['qty_ordered'] : 1));

                    $swo->items()->create([
                        'so_line_id' => $soLineId,
                        'catalog_item_id' => $el['catalog_item_id'] ?? null,
                        'qty_ordered' => $qty,
                        'qty_completed' => 0,
                        'status' => 'pending',
                        'notes' => $reqLine['notes'] ?? null,
                        'created_by' => $actor?->id,
                    ]);
                }
            }

            // Record initial history
            SwoStatusHistory::create([
                'swo_id' => $swo->id,
                'from_status' => null,
                'to_status' => SwoStatus::Draft->value,
                'changed_by' => $actor?->id,
                'notes' => 'SWO created from Sales Order '.$so->reference,
            ]);

            return $swo;
        });
    }

    /**
     * Attach locations to SWO.
     */
    public function attachLocations(ServiceWorkOrder $swo, array $siteIds): void
    {
        SwoLocation::where('swo_id', $swo->id)->delete();
        $order = 0;
        foreach (array_unique($siteIds) as $siteId) {
            if ($siteId) {
                SwoLocation::create([
                    'swo_id' => $swo->id,
                    'client_site_id' => $siteId,
                    'sort_order' => $order++,
                ]);
            }
        }
    }

    /**
     * Attach individual technicians to SWO.
     */
    public function attachEmployees(ServiceWorkOrder $swo, array $employeeIds, ?string $leadEmployeeId = null): void
    {
        SwoEmployee::where('swo_id', $swo->id)->delete();
        foreach (array_unique($employeeIds) as $empId) {
            if ($empId) {
                SwoEmployee::create([
                    'swo_id' => $swo->id,
                    'employee_id' => $empId,
                    'is_lead' => $empId === $leadEmployeeId,
                ]);
            }
        }
    }

    /**
     * Transition SWO status with validation and lifecycle side effects.
     */
    public function transition(ServiceWorkOrder $swo, SwoStatus $targetStatus, ?User $actor = null, array $payload = []): ServiceWorkOrder
    {
        $currentStatus = $swo->status;

        if ($currentStatus === $targetStatus) {
            return $swo;
        }

        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw ValidationException::withMessages([
                'status' => [
                    "Cannot transition SWO from '{$currentStatus->label()}' to '{$targetStatus->label()}'.",
                ],
            ]);
        }

        return DB::transaction(function () use ($swo, $currentStatus, $targetStatus, $actor, $payload) {
            $updates = [
                'status' => $targetStatus,
                'updated_by' => $actor?->id,
            ];

            if ($targetStatus === SwoStatus::InProgress && ! $swo->started_at) {
                $updates['started_at'] = now();
            }

            if ($targetStatus === SwoStatus::Completed) {
                $updates['completed_at'] = now();
            }

            if ($targetStatus === SwoStatus::Closed) {
                $updates['closed_at'] = now();
            }

            if ($targetStatus === SwoStatus::Cancelled) {
                $updates['cancellation_reason'] = $payload['cancellation_reason'] ?? $payload['notes'] ?? 'Cancelled by user';
            }

            $swo->update($updates);

            // When SWO is completed, mark all its items as completed (qty_completed = qty_ordered for EACH item)
            // This enables fulfillment tracking based on actual operational completion
            if ($targetStatus === SwoStatus::Completed) {
                $swo->items()->update([
                    'qty_completed' => DB::raw('qty_ordered'),
                    'status' => 'completed',
                ]);
            }

            // Record history
            SwoStatusHistory::create([
                'swo_id' => $swo->id,
                'from_status' => $currentStatus->value,
                'to_status' => $targetStatus->value,
                'changed_by' => $actor?->id,
                'notes' => $payload['notes'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            // Ensure exactly ONE primary mission exists once the SWO is planned/validated.
            if (in_array($targetStatus, [SwoStatus::Planned, SwoStatus::Scheduled, SwoStatus::InProgress], true)) {
                $this->ensurePrimaryMission($swo, $actor);
            }

            // If completed or cancelled, check if parent Sales Order can be closed or status updated
            if (in_array($targetStatus, [SwoStatus::Completed, SwoStatus::Cancelled], true)) {
                if ($swo->salesOrder) {
                    app(SalesOrderService::class)->checkAndCloseSalesOrder($swo->salesOrder);
                }
            }

            return $swo->fresh(['items', 'team', 'leadTechnician', 'workshopUnit', 'vehicle', 'locations.clientSite', 'employees', 'missions']);
        });
    }

    /**
     * Ensure the SWO owns exactly ONE primary mission.
     *
     * The manager never has to manually create/dispatch a second mission: once
     * the SWO is validated/planned, a single primary mission inherits the SWO
     * configuration. Additional missions remain possible but are the exception.
     */
    public function ensurePrimaryMission(ServiceWorkOrder $swo, ?User $actor = null): ?Mission
    {
        if ($swo->missions()->exists()) {
            return $swo->missions()->first();
        }

        $missionMode = ($swo->fulfillment_mode?->isWorkshop() ?? false)
            ? MissionMode::Workshop
            : MissionMode::FieldService;

        return app(MissionService::class)->createMission($swo, [
            'mode' => $missionMode->value,
            'service_site_id' => $swo->service_site_id,
            'workshop_unit_id' => $swo->workshop_unit_id,
            'team_id' => $swo->team_id,
            'lead_technician_id' => $swo->lead_technician_id,
            'vehicle_id' => $swo->vehicle_id,
            'approved_driver_id' => $swo->approved_driver_id,
            'mobile_warehouse_id' => $swo->mobile_warehouse_id,
            'planned_date' => $swo->planned_date ?? $swo->scheduled_date ?? now()->toDateString(),
            'planned_start' => $swo->planned_start,
            'planned_end' => $swo->planned_end,
            'planned_start_time' => $swo->planned_start_time,
            'planned_duration_hours' => $swo->planned_duration_hours,
            'preparation_notes' => 'Primary mission auto-created upon SWO validation',
        ], $actor);
    }

    /**
     * Schedule or plan SWO assignment.
     */
    public function scheduleOrPlan(ServiceWorkOrder $swo, array $data, ?User $actor = null): ServiceWorkOrder
    {
        return DB::transaction(function () use ($swo, $data, $actor) {
            $fields = [
                'planned_date' => $data['planned_date'] ?? $swo->planned_date,
                'planned_start' => $data['planned_start'] ?? $swo->planned_start,
                'planned_end' => $data['planned_end'] ?? $swo->planned_end,
                'scheduled_date' => $data['scheduled_date'] ?? $data['planned_date'] ?? $swo->scheduled_date,
                'planned_start_time' => $data['planned_start_time'] ?? $swo->planned_start_time,
                'planned_duration_hours' => $data['planned_duration_hours'] ?? $swo->planned_duration_hours,
                'team_id' => array_key_exists('team_id', $data) ? $data['team_id'] : $swo->team_id,
                'lead_technician_id' => array_key_exists('lead_technician_id', $data) ? $data['lead_technician_id'] : $swo->lead_technician_id,
                'workshop_unit_id' => array_key_exists('workshop_unit_id', $data) ? $data['workshop_unit_id'] : $swo->workshop_unit_id,
                'vehicle_id' => array_key_exists('vehicle_id', $data) ? $data['vehicle_id'] : $swo->vehicle_id,
                'approved_driver_id' => array_key_exists('approved_driver_id', $data) ? $data['approved_driver_id'] : $swo->approved_driver_id,
                'mobile_warehouse_id' => array_key_exists('mobile_warehouse_id', $data) ? $data['mobile_warehouse_id'] : $swo->mobile_warehouse_id,
                'checklist_template_id' => array_key_exists('checklist_template_id', $data) ? $data['checklist_template_id'] : $swo->checklist_template_id,
                'notes' => $data['notes'] ?? $swo->notes,
                'updated_by' => $actor?->id,
            ];

            $swo->update($fields);

            if (! empty($data['site_ids']) && is_array($data['site_ids'])) {
                $this->attachLocations($swo, $data['site_ids']);
            }

            if (! empty($data['employee_ids']) && is_array($data['employee_ids'])) {
                $this->attachEmployees($swo, $data['employee_ids'], $data['lead_technician_id'] ?? null);
            }

            // Auto-advance status to Planned or Scheduled if currently Draft or ReadyForPlanning
            if (in_array($swo->status, [SwoStatus::Draft, SwoStatus::Accepted, SwoStatus::ReadyForPlanning], true)) {
                $target = isset($data['scheduled_date']) ? SwoStatus::Scheduled : SwoStatus::Planned;
                if ($swo->status->canTransitionTo($target)) {
                    $this->transition($swo, $target, $actor, ['notes' => 'Planning and assignment updated']);
                }
            }

            return $swo->fresh(['locations.clientSite', 'employees', 'workshopUnit', 'missions']);
        });
    }

    /**
     * Operational Scope / Equipment Explorer feed.
     *
     * Returns the SWO's selected sites, their zones (areas) and equipment with
     * the live intervention / workshop status of each unit. This is the source
     * of truth for operational tracking (NOT Sales Order line quantities).
     */
    public function getOperationalScope(ServiceWorkOrder $swo, ?string $missionId = null): array
    {
        $swo->loadMissing(['locations.clientSite', 'missions']);

        $interventions = Intervention::query()
            ->whereHas('mission', function ($q) use ($swo, $missionId) {
                $q->where('swo_id', $swo->id);
                if ($missionId) {
                    $q->where('id', $missionId);
                }
            })
            ->get();

        $interventionsByEquipment = $interventions->groupBy('equipment_id');

        // Equipment already transferred to a workshop (active exit passes).
        $activePassIds = WorkshopExitPass::where('swo_id', $swo->id)
            ->whereIn('status', ['pending', 'confirmed', 'received'])
            ->pluck('id');

        $transferredEquipmentIds = WorkshopExitPassEquipment::whereIn('exit_pass_id', $activePassIds)
            ->pluck('equipment_id')
            ->unique()
            ->all();

        $receivedEquipmentIds = \App\Modules\OperationalHub\Models\WorkshopReception::query()
            ->whereIn('exit_pass_id', WorkshopExitPass::where('swo_id', $swo->id)->pluck('id'))
            ->pluck('equipment_id')
            ->unique()
            ->all();

        $sites = $swo->locations->map(function ($location) use ($interventionsByEquipment, $transferredEquipmentIds, $receivedEquipmentIds) {
            $site = $location->clientSite;
            if (! $site) {
                return null;
            }

            $equipment = \App\Modules\ClientAssets\Models\ClientEquipment::query()
                ->where('site_id', $site->id)
                ->with('area')
                ->orderBy('equipment_code')
                ->get();

            $mapped = $equipment->map(function ($eq) use ($interventionsByEquipment, $transferredEquipmentIds, $receivedEquipmentIds) {
                $rows = $interventionsByEquipment->get($eq->id) ?? collect();
                $open = $rows->firstWhere('status', '!=', 'completed');
                $completed = $rows->firstWhere('status', 'completed');

                return [
                    'id' => $eq->id,
                    'equipment_code' => $eq->equipment_code,
                    'serial_number' => $eq->serial_number,
                    'model_name' => $eq->model_name,
                    'status' => $eq->status,
                    'condition_status' => $eq->condition_status,
                    'compliance_status' => $eq->compliance_status,
                    'area_id' => $eq->area_id,
                    'area_name' => $eq->area?->name,
                    'intervention_id' => $open?->id ?? $completed?->id,
                    'intervention_status' => $open?->status ?? ($completed ? 'completed' : null),
                    'intervention_result' => $completed?->result,
                    'has_intervention' => $rows->isNotEmpty(),
                    'is_completed' => $rows->contains(fn ($r) => $r->status === 'completed'),
                    'transferred' => in_array($eq->id, $transferredEquipmentIds, true),
                    'received_in_workshop' => in_array($eq->id, $receivedEquipmentIds, true),
                ];
            });

            // Group equipment into zones (areas) for the explorer UI.
            $zones = $mapped->groupBy(fn ($e) => $e['area_id'] ?? 'unassigned')
                ->map(fn ($items, $areaId) => [
                    'area_id' => $areaId === 'unassigned' ? null : $areaId,
                    'area_name' => $items->first()['area_name'] ?? 'Unassigned zone',
                    'equipment' => $items->values(),
                ])
                ->values();

            return [
                'site_id' => $site->id,
                'site_name' => $site->name,
                'address' => $site->address,
                'zones' => $zones,
                'totals' => [
                    'equipment' => $mapped->count(),
                    'completed' => $mapped->where('is_completed', true)->count(),
                    'pending' => $mapped->where('has_intervention', false)->count() + $mapped->where('intervention_status', '!=', 'completed')->where('has_intervention', true)->count(),
                    'transferred' => $mapped->where('transferred', true)->count(),
                ],
            ];
        })->filter()->values();

        return [
            'swo_id' => $swo->id,
            'swo_number' => $swo->swo_number,
            'mission_id' => $missionId,
            'sites' => $sites,
            'totals' => [
                'sites' => $sites->count(),
                'equipment' => $sites->sum(fn ($s) => $s['totals']['equipment']),
                'completed' => $sites->sum(fn ($s) => $s['totals']['completed']),
                'transferred' => $sites->sum(fn ($s) => $s['totals']['transferred']),
            ],
        ];
    }

    /**
     * Get operational summary statistics for SWO Tracker page.
     */
    public function getSwoStats(ServiceWorkOrder $swo): array
    {
        $swo->loadMissing([
            'missions.interventions.equipment',
            'locations.clientSite',
            'operationalCosts',
            'exitPasses',
        ]);

        $interventions = Intervention::whereHas('mission', function ($q) use ($swo) {
            $q->where('swo_id', $swo->id);
        })->get();

        $totalInterventions = $interventions->count();
        $completedInterventions = $interventions->where('status', 'completed')->count();
        $pendingInterventions = $totalInterventions - $completedInterventions;
        $progressPercent = $totalInterventions > 0 ? (int) round(($completedInterventions / $totalInterventions) * 100) : 0;

        // Exit passes & transferred equipment count
        $activePassIds = WorkshopExitPass::where('swo_id', $swo->id)
            ->whereIn('status', ['pending', 'confirmed', 'received'])
            ->pluck('id');

        $transferredEquipmentCount = WorkshopExitPassEquipment::whereIn('exit_pass_id', $activePassIds)
            ->distinct('equipment_id')
            ->count('equipment_id');

        // Total equipment involved in interventions
        $equipmentIds = $interventions->pluck('equipment_id')->filter()->unique();
        $totalEquipmentCount = $equipmentIds->count();

        // Operational Costs
        $plannedCost = (float) $swo->operationalCosts->sum('planned_amount');
        $actualCost = (float) $swo->operationalCosts->sum('actual_amount');

        // Missions stats
        $missionsCount = $swo->missions->count();
        $completedMissionsCount = $swo->missions->filter(fn ($m) => in_array($m->status?->value ?? (string) $m->status, ['completed', 'closed'], true))->count();

        // Documentation stats
        $docStats = app(ServiceDeliveryNoteService::class)->getDocumentableStats($swo);

        return [
            'swo_id' => $swo->id,
            'swo_number' => $swo->swo_number,
            'status' => $swo->status?->value,
            'fulfillment_mode' => $swo->fulfillment_mode?->value,
            'total_equipment_count' => $totalEquipmentCount,
            'completed_equipment_count' => $completedInterventions,
            'pending_equipment_count' => $pendingInterventions,
            'transferred_equipment_count' => $transferredEquipmentCount,
            'progress_percentage' => $progressPercent,
            'total_missions' => $missionsCount,
            'completed_missions' => $completedMissionsCount,
            'planned_cost' => $plannedCost,
            'actual_cost' => $actualCost,
            'currency' => $swo->operationalCosts->first()?->currency ?? 'TND',
            'documentation' => $docStats,
            'locations_count' => $swo->locations->count(),
        ];
    }

    /**
     * Validate that SWO lines don't exceed available SO line quantities.
     * Prevents over-allocation across multiple SWOs.
     *
     * @param  array<int, mixed>  $requestedLines
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateSoLineQuantities(SalesOrder $so, array $requestedLines): void
    {
        $so->loadMissing('lines');

        // Calculate already allocated quantities from existing SWOs
        $allocatedQuantities = $this->getAllocatedQuantitiesBySoLine($so);

        foreach ($requestedLines as $idx => $line) {
            $soLineId = $line['so_line_id'] ?? $line['id'] ?? null;
            $requestedQty = (float) ($line['qty'] ?? $line['qty_ordered'] ?? 0);

            if (! $soLineId || $requestedQty <= 0) {
                continue;
            }

            $soLine = $so->lines->firstWhere('id', $soLineId);
            if (! $soLine) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "lines.{$idx}.so_line_id" => 'Invalid SO line reference.',
                ]);
            }

            $alreadyAllocated = (float) ($allocatedQuantities[$soLineId] ?? 0);
            $remaining = (float) $soLine->qty - $alreadyAllocated;

            if ($requestedQty > $remaining + 0.0001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "lines.{$idx}.qty" => "Requested quantity {$requestedQty} exceeds remaining available quantity {$remaining} on SO line #{$soLineId}.",
                ]);
            }
        }
    }

    /**
     * Get total allocated quantities per SO line from existing SWOs.
     *
     * @return array<string, float>
     */
    private function getAllocatedQuantitiesBySoLine(SalesOrder $so): array
    {
        return SwoItem::query()
            ->whereHas('serviceWorkOrder', function ($q) use ($so) {
                $q->where('sales_order_id', $so->id)
                    ->whereNotIn('status', ['cancelled']);
            })
            ->selectRaw('so_line_id, SUM(qty_ordered) as total_allocated')
            ->groupBy('so_line_id')
            ->get()
            ->keyBy('so_line_id')
            ->map(fn ($item) => (float) $item->total_allocated)
            ->toArray();
    }
}
