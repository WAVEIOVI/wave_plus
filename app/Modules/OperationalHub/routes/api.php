<?php

declare(strict_types=1);

use App\Modules\OperationalHub\Controllers\ChecklistTemplateController;
use App\Modules\OperationalHub\Controllers\InterventionController;
use App\Modules\OperationalHub\Controllers\MissionController;
use App\Modules\OperationalHub\Controllers\OperationalForecastController;
use App\Modules\OperationalHub\Controllers\OperationalHubDashboardController;
use App\Modules\OperationalHub\Controllers\ServiceDeliveryNoteController;
use App\Modules\OperationalHub\Controllers\ServiceWorkOrderController;
use App\Modules\OperationalHub\Controllers\WorkshopDashboardController;
use App\Modules\OperationalHub\Controllers\WorkshopExitPassController;
use App\Modules\OperationalHub\Controllers\WorkshopReceptionController;
use App\Modules\OperationalHub\Controllers\WorkshopUnitController;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\OperationalHub\Resources\MissionListResource;
use App\Modules\OperationalHub\Resources\ServiceWorkOrderListResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Operational Hub & Workshop API Routes
|--------------------------------------------------------------------------
|
| Prefixed with /api/operational-hub and protected by auth:sanctum middleware.
|
*/

Route::prefix('operational-hub')
    ->middleware(['auth:sanctum'])
    ->group(function () {

        // ── Dashboards ──────────────────────────────────────────────
        Route::get('dashboard', [OperationalHubDashboardController::class, 'index']);
        Route::get('workshop-dashboard', [WorkshopDashboardController::class, 'index']);

        // ── Planning Calendar Feed ──────────────────────────────────
        Route::get('planning/calendar', function (Request $request) {
            $missionsQuery = Mission::query()
                ->with(['swo.client', 'team', 'leadTechnician', 'workshopUnit'])
                ->whereNotNull('planned_date');

            if ($request->filled('start')) {
                $missionsQuery->whereDate('planned_date', '>=', $request->input('start'));
            }
            if ($request->filled('end')) {
                $missionsQuery->whereDate('planned_date', '<=', $request->input('end'));
            }

            $missions = $missionsQuery->get();

            $events = $missions->map(function ($m) {
                $time = $m->planned_start_time ? 'T' . $m->planned_start_time : '';
                $clientName = $m->swo?->client?->commercial_name ?? $m->swo?->client?->legal_name ?? 'Client';
                $color = match ($m->status?->value) {
                    'started', 'in_progress' => '#ff9f43',
                    'completed'              => '#28c76f',
                    'paused'                 => '#ea5455',
                    default                  => '#7367f0',
                };

                return [
                    'id'               => 'mission_' . $m->id,
                    'title'            => "{$m->mission_number} - {$clientName}",
                    'start'            => $m->planned_date?->format('Y-m-d') . $time,
                    'allDay'           => empty($m->planned_start_time),
                    'backgroundColor'  => $color,
                    'borderColor'      => $color,
                    'extendedProps'    => [
                        'type'            => 'mission',
                        'mission_id'      => $m->id,
                        'swo_id'          => $m->swo_id,
                        'swo_number'      => $m->swo?->swo_number,
                        'mode'            => $m->mode?->value,
                        'status'          => $m->status?->value,
                        'client_name'     => $clientName,
                        'team_name'       => $m->team?->name,
                        'technician_name' => $m->leadTechnician ? $m->leadTechnician->first_name . ' ' . $m->leadTechnician->last_name : null,
                    ],
                ];
            });

            return response()->json($events);
        });

        // ── Field Service Vehicles (mobile workshop = a vehicle config) ──
        Route::get('vehicles', [ServiceWorkOrderController::class, 'vehicles']);

        // ── Service Work Orders (SWO) ───────────────────────────────
        Route::get('swos/meta', [ServiceWorkOrderController::class, 'meta']);
        Route::get('swos/kanban', [ServiceWorkOrderController::class, 'kanban']);
        Route::get('swos/eligible-so-lines/{salesOrder}', [ServiceWorkOrderController::class, 'eligibleSoLines']);
        Route::post('swos/from-so', [ServiceWorkOrderController::class, 'createFromSo']);
        Route::get('swos', [ServiceWorkOrderController::class, 'index']);
        Route::get('swos/{serviceWorkOrder}', [ServiceWorkOrderController::class, 'show']);
        Route::put('swos/{serviceWorkOrder}', [ServiceWorkOrderController::class, 'update']);
        Route::delete('swos/{serviceWorkOrder}', [ServiceWorkOrderController::class, 'destroy']);
        Route::patch('swos/{serviceWorkOrder}/transition', [ServiceWorkOrderController::class, 'transition']);
        Route::get('swos/{serviceWorkOrder}/stats', [ServiceWorkOrderController::class, 'stats']);
        Route::get('swos/{serviceWorkOrder}/scope', [ServiceWorkOrderController::class, 'scope']);
        Route::get('swos/{serviceWorkOrder}/locations', [ServiceWorkOrderController::class, 'locations']);
        Route::post('swos/{serviceWorkOrder}/locations', [ServiceWorkOrderController::class, 'updateLocations']);
        Route::get('swos/{serviceWorkOrder}/employees', [ServiceWorkOrderController::class, 'employees']);
        Route::post('swos/{serviceWorkOrder}/employees', [ServiceWorkOrderController::class, 'updateEmployees']);
        Route::get('swos/{serviceWorkOrder}/operational-costs', [ServiceWorkOrderController::class, 'operationalCosts']);
        Route::post('swos/{serviceWorkOrder}/operational-costs', [ServiceWorkOrderController::class, 'storeOperationalCost']);
        Route::delete('swos/{serviceWorkOrder}/operational-costs/{cost}', [ServiceWorkOrderController::class, 'destroyOperationalCost']);
        Route::get('swos/{serviceWorkOrder}/missions', [ServiceWorkOrderController::class, 'missions']);
        Route::get('swos/{serviceWorkOrder}/documentable-stats', [ServiceDeliveryNoteController::class, 'documentableStats']);
        Route::post('swos/{serviceWorkOrder}/delivery-note', [ServiceDeliveryNoteController::class, 'generate']);

        // ── Missions ────────────────────────────────────────────────
        Route::get('missions', [MissionController::class, 'index']);
        Route::get('missions/{mission}', [MissionController::class, 'show']);
        Route::post('missions', [MissionController::class, 'store']);
        Route::put('missions/{mission}', [MissionController::class, 'update']);
        Route::patch('missions/{mission}/transition', [MissionController::class, 'transition']);
        Route::get('missions/{mission}/stats', [MissionController::class, 'stats']);
        Route::get('missions/{mission}/scope', [MissionController::class, 'scope']);
        Route::get('missions/{mission}/employees', [MissionController::class, 'employees']);
        Route::post('missions/{mission}/employees', [MissionController::class, 'updateEmployees']);
        Route::get('missions/{mission}/exit-passes', [WorkshopExitPassController::class, 'indexForMission']);
        Route::post('missions/{mission}/exit-passes', [WorkshopExitPassController::class, 'storeForMission']);
        Route::get('missions/{mission}/transfer-stats', [WorkshopExitPassController::class, 'transferStats']);
        Route::post('missions/{mission}/attendance', [MissionController::class, 'recordAttendance']);
        Route::post('missions/{mission}/checklist', [MissionController::class, 'saveChecklist']);
        Route::get('missions/{mission}/interventions', [MissionController::class, 'interventions']);
        Route::post('missions/{mission}/interventions', [InterventionController::class, 'store']);
        Route::get('missions/{mission}/findings', [MissionController::class, 'findings']);
        Route::post('missions/{mission}/workshop-transfer', [MissionController::class, 'workshopTransfer']);

        // ── Interventions ───────────────────────────────────────────
        Route::get('interventions', [InterventionController::class, 'index']);
        Route::get('interventions/{intervention}', [InterventionController::class, 'show']);
        Route::put('interventions/{intervention}', [InterventionController::class, 'update']);
        Route::patch('interventions/{intervention}/complete', [InterventionController::class, 'complete']);
        Route::post('interventions/{intervention}/materials', [InterventionController::class, 'addMaterial']);
        Route::post('interventions/{intervention}/findings', [InterventionController::class, 'addFinding']);

        // ── Workshop Units ──────────────────────────────────────────
        Route::get('workshop-units', [WorkshopUnitController::class, 'index']);
        Route::get('workshop-units/{workshopUnit}', [WorkshopUnitController::class, 'show']);
        Route::post('workshop-units', [WorkshopUnitController::class, 'store']);
        Route::put('workshop-units/{workshopUnit}', [WorkshopUnitController::class, 'update']);
        Route::delete('workshop-units/{workshopUnit}', [WorkshopUnitController::class, 'destroy']);

        // ── Workshop Receptions ─────────────────────────────────────
        Route::get('workshop-receptions', [WorkshopReceptionController::class, 'index']);
        Route::get('workshop-receptions/{workshopReception}', [WorkshopReceptionController::class, 'show']);
        Route::post('workshop-receptions', [WorkshopReceptionController::class, 'store']);
        Route::put('workshop-receptions/{workshopReception}', [WorkshopReceptionController::class, 'update']);
        Route::post('workshop-receptions/{workshopReception}/confirm', [WorkshopReceptionController::class, 'confirm']);

        // ── Workshop Exit Passes ────────────────────────────────────
        Route::get('exit-passes', [WorkshopExitPassController::class, 'index']);
        Route::get('exit-passes/{exitPass}', [WorkshopExitPassController::class, 'show']);
        Route::post('exit-passes', [WorkshopExitPassController::class, 'store']);
        Route::patch('exit-passes/{exitPass}/status', [WorkshopExitPassController::class, 'updateStatus']);

        // ── Checklist Templates ─────────────────────────────────────
        Route::get('checklist-templates', [ChecklistTemplateController::class, 'index']);
        Route::get('checklist-templates/{template}', [ChecklistTemplateController::class, 'show']);
        Route::post('checklist-templates', [ChecklistTemplateController::class, 'store']);
        Route::put('checklist-templates/{template}', [ChecklistTemplateController::class, 'update']);
        Route::delete('checklist-templates/{template}', [ChecklistTemplateController::class, 'destroy']);

        // ── Service Delivery Notes ──────────────────────────────────
        Route::get('delivery-notes', [ServiceDeliveryNoteController::class, 'index']);
        Route::get('delivery-notes/{serviceDeliveryNote}', [ServiceDeliveryNoteController::class, 'show']);
        Route::patch('delivery-notes/{serviceDeliveryNote}/sign', [ServiceDeliveryNoteController::class, 'sign']);

        // ── Operational Forecasts ───────────────────────────────────
        Route::get('forecasts/summary', [OperationalForecastController::class, 'summary']);
        Route::get('forecasts', [OperationalForecastController::class, 'index']);
        Route::get('forecasts/{operationalForecast}', [OperationalForecastController::class, 'show']);
        Route::post('forecasts', [OperationalForecastController::class, 'store']);
        Route::put('forecasts/{operationalForecast}', [OperationalForecastController::class, 'update']);
    });
