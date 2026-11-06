<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OperationalHub\Enums\SwoFulfillmentMode;
use App\Modules\OperationalHub\Enums\SwoStatus;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\OperationalForecast;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\OperationalHub\Resources\OperationalForecastResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OperationalForecastController extends Controller
{
    use AuthorizesRequests;

    /**
     * Forecast built from REAL operational data (upcoming SWOs/missions,
     * workload, planned equipment volume, team/workshop load, overdue ops).
     * No fabricated charts.
     */
    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceWorkOrder::class);

        $today = now()->toDateString();
        $horizon = now()->addDays((int) $request->input('days', 30))->toDateString();

        $upcomingSwoQuery = ServiceWorkOrder::query()
            ->whereIn('status', [SwoStatus::Planned->value, SwoStatus::Scheduled->value])
            ->whereNotNull('planned_date')
            ->whereDate('planned_date', '>=', $today)
            ->whereDate('planned_date', '<=', $horizon)
            ->with(['client', 'workshopUnit']);

        $upcomingSwos = $upcomingSwoQuery->get();

        $upcomingMissions = Mission::query()
            ->whereNotNull('planned_date')
            ->whereDate('planned_date', '>=', $today)
            ->whereDate('planned_date', '<=', $horizon)
            ->whereIn('mode', [\App\Modules\OperationalHub\Enums\MissionMode::FieldService->value, \App\Modules\OperationalHub\Enums\MissionMode::Workshop->value])
            ->whereNotIn('status', [\App\Modules\OperationalHub\Enums\MissionStatus::Completed->value, \App\Modules\OperationalHub\Enums\MissionStatus::Cancelled->value, \App\Modules\OperationalHub\Enums\MissionStatus::Closed->value])
            ->get();

        // Planned equipment volume: distinct client equipment across the sites of upcoming SWOs.
        $siteIds = \App\Modules\OperationalHub\Models\SwoLocation::query()
            ->whereIn('swo_id', $upcomingSwos->pluck('id'))
            ->pluck('client_site_id')
            ->unique()
            ->filter()
            ->values();

        $plannedEquipmentVolume = $siteIds->isEmpty()
            ? 0
            : \App\Modules\ClientAssets\Models\ClientEquipment::whereIn('site_id', $siteIds)->count();

        $teamWorkload = $upcomingSwos->groupBy(fn ($swo) => $swo->team_id ?? 'unassigned')
            ->map(fn ($items, $teamId) => [
                'team_id' => $teamId === 'unassigned' ? null : $teamId,
                'count' => $items->count(),
                'hours' => (float) $items->sum(fn ($s) => (float) $s->planned_duration_hours),
            ])
            ->values();

        $workshopWorkload = $upcomingSwos->where('fulfillment_mode', SwoFulfillmentMode::Workshop)
            ->groupBy(fn ($swo) => $swo->workshop_unit_id ?? 'unassigned')
            ->map(fn ($items, $unitId) => [
                'workshop_unit_id' => $unitId === 'unassigned' ? null : $unitId,
                'count' => $items->count(),
            ])
            ->values();

        $overdueMissions = Mission::query()
            ->whereNotNull('planned_date')
            ->whereDate('planned_date', '<', $today)
            ->whereNotIn('status', [\App\Modules\OperationalHub\Enums\MissionStatus::Completed->value, \App\Modules\OperationalHub\Enums\MissionStatus::Cancelled->value, \App\Modules\OperationalHub\Enums\MissionStatus::Closed->value])
            ->with(['swo.client'])
            ->orderBy('planned_date')
            ->limit(25)
            ->get();

        $expectedCompletion = ServiceWorkOrder::query()
            ->where('status', SwoStatus::InProgress->value)
            ->max('planned_end');

        return response()->json([
            'data' => [
                'horizon' => [
                    'from' => $today,
                    'to' => $horizon,
                    'days' => (int) $request->input('days', 30),
                ],
                'stats' => [
                    'upcoming_swos' => $upcomingSwos->count(),
                    'upcoming_missions' => $upcomingMissions->count(),
                    'planned_hours' => (float) $upcomingSwos->sum(fn ($s) => (float) $s->planned_duration_hours),
                    'planned_equipment_volume' => $plannedEquipmentVolume,
                    'overdue_operations' => $overdueMissions->count(),
                    'expected_completion' => $expectedCompletion,
                ],
                'team_workload' => $teamWorkload,
                'workshop_workload' => $workshopWorkload,
                'upcoming_swos' => $upcomingSwos->map(fn ($s) => [
                    'id' => $s->id,
                    'swo_number' => $s->swo_number,
                    'client_name' => $s->client?->commercial_name ?? $s->client?->legal_name,
                    'planned_date' => $s->planned_date?->format('Y-m-d'),
                    'fulfillment_mode' => $s->fulfillment_mode?->value,
                    'status' => $s->status?->value,
                ])->values(),
                'upcoming_missions' => $upcomingMissions->map(fn ($m) => [
                    'id' => $m->id,
                    'mission_number' => $m->mission_number,
                    'mode' => $m->mode?->value,
                    'planned_date' => $m->planned_date?->format('Y-m-d'),
                    'status' => $m->status?->value,
                ])->values(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OperationalForecast::class);

        $query = OperationalForecast::query()
            ->with(['client', 'equipment', 'serviceSite', 'convertedSwo']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }
        if ($request->filled('forecast_type')) {
            $query->where('forecast_type', $request->input('forecast_type'));
        }

        // Sorting
        $allowedSortFields = ['expected_date', 'created_at', 'title'];
        $sortBy = $request->input('sortBy', 'expected_date');
        $sortBy = in_array($sortBy, $allowedSortFields, true) ? $sortBy : 'expected_date';
        
        $sortOrder = in_array($request->input('sortOrder'), ['asc', 'desc'], true) 
            ? $request->input('sortOrder') 
            : 'asc';

        $perPage = (int) $request->input('itemsPerPage', $request->input('per_page', 15));
        $forecasts = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

        return OperationalForecastResource::collection($forecasts);
    }

    public function show(OperationalForecast $operationalForecast): JsonResponse
    {
        $this->authorize('view', $operationalForecast);

        $operationalForecast->load(['client', 'equipment', 'serviceSite', 'convertedSwo']);

        return response()->json([
            'data' => new OperationalForecastResource($operationalForecast),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', OperationalForecast::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'client_id' => 'required|ulid|exists:clients,id',
            'equipment_id' => 'nullable|ulid|exists:client_equipment,id',
            'service_site_id' => 'nullable|ulid|exists:client_sites,id',
            'forecast_type' => 'nullable|string|max:50',
            'expected_date' => 'required|date',
            'estimated_hours' => 'nullable|numeric|gte:0',
            'estimated_technicians' => 'nullable|integer|gte:1',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:draft,confirmed,converted,dismissed',
        ]);

        $forecast = OperationalForecast::create(array_merge($validated, [
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]));

        return response()->json([
            'message' => 'Operational forecast created successfully.',
            'data' => new OperationalForecastResource($forecast->fresh(['client', 'equipment', 'serviceSite'])),
        ], 201);
    }

    public function update(Request $request, OperationalForecast $operationalForecast): JsonResponse
    {
        $this->authorize('update', $operationalForecast);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'client_id' => 'sometimes|ulid|exists:clients,id',
            'equipment_id' => 'nullable|ulid|exists:client_equipment,id',
            'service_site_id' => 'nullable|ulid|exists:client_sites,id',
            'forecast_type' => 'sometimes|string|max:50',
            'expected_date' => 'sometimes|date',
            'estimated_hours' => 'nullable|numeric|gte:0',
            'estimated_technicians' => 'nullable|integer|gte:1',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:draft,confirmed,converted,dismissed',
        ]);

        $operationalForecast->update(array_merge($validated, [
            'updated_by' => $request->user()?->id,
        ]));

        return response()->json([
            'message' => 'Operational forecast updated successfully.',
            'data' => new OperationalForecastResource($operationalForecast->fresh(['client', 'equipment', 'serviceSite'])),
        ]);
    }
}
