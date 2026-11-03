<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\OperationalHub\Enums\SwoFulfillmentMode;
use App\Modules\OperationalHub\Enums\SwoStatus;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\OperationalHub\Requests\CreateSwoFromSoRequest;
use App\Modules\OperationalHub\Requests\TransitionSwoRequest;
use App\Modules\OperationalHub\Requests\UpdateSwoRequest;
use App\Modules\OperationalHub\Resources\MissionListResource;
use App\Modules\OperationalHub\Resources\ServiceWorkOrderListResource;
use App\Modules\OperationalHub\Resources\ServiceWorkOrderResource;
use App\Modules\OperationalHub\Services\SwoService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceWorkOrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SwoService $swoService,
    ) {}

    /**
     * Dashboard / Kanban metadata counters.
     */
    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', ServiceWorkOrder::class);

        return response()->json([
            'data' => [
                'total_swos'          => ServiceWorkOrder::count(),
                'draft'               => ServiceWorkOrder::where('status', SwoStatus::Draft->value)->count(),
                'accepted'            => ServiceWorkOrder::where('status', SwoStatus::Accepted->value)->count(),
                'ready_for_planning'  => ServiceWorkOrder::where('status', SwoStatus::ReadyForPlanning->value)->count(),
                'planned'             => ServiceWorkOrder::where('status', SwoStatus::Planned->value)->count(),
                'scheduled'           => ServiceWorkOrder::where('status', SwoStatus::Scheduled->value)->count(),
                'in_progress'         => ServiceWorkOrder::where('status', SwoStatus::InProgress->value)->count(),
                'partially_completed' => ServiceWorkOrder::where('status', SwoStatus::PartiallyCompleted->value)->count(),
                'completed'           => ServiceWorkOrder::where('status', SwoStatus::Completed->value)->count(),
                'cancelled'           => ServiceWorkOrder::where('status', SwoStatus::Cancelled->value)->count(),
                'closed'              => ServiceWorkOrder::where('status', SwoStatus::Closed->value)->count(),
                'field_service'       => ServiceWorkOrder::where('fulfillment_mode', SwoFulfillmentMode::FieldService->value)->count(),
                'workshop'            => ServiceWorkOrder::where('fulfillment_mode', SwoFulfillmentMode::Workshop->value)->count(),
            ],
        ]);
    }

    /**
     * Field-service vehicles. A vehicle whose type is "mobile_workshop" is a
     * field-service configuration, NOT a separate fulfillment mode.
     */
    public function vehicles(): JsonResponse
    {
        $this->authorize('viewAny', ServiceWorkOrder::class);

        $vehicles = \App\Modules\Hr\Models\Vehicle::query()
            ->orderBy('plate_number')
            ->get()
            ->map(fn ($vehicle) => [
                'id'                => $vehicle->id,
                'plate_number'      => $vehicle->plate_number,
                'label'             => $vehicle->label,
                'type'              => $vehicle->type,
                'status'            => $vehicle->status,
                'is_mobile_workshop' => $vehicle->type === 'mobile_workshop',
            ])
            ->values();

        return response()->json(['data' => $vehicles]);
    }

    /**
     * Paginated index with rich filtering.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ServiceWorkOrder::class);

        $query = ServiceWorkOrder::query()
            ->with(['client', 'serviceSite', 'team', 'leadTechnician', 'workshopUnit', 'vehicle', 'salesOrder'])
            ->withCount(['items', 'missions']);

        // Filters — status supports a single value OR comma-separated values (workflow buckets)
        $rawStatus = $request->input('status') ?? $request->input('filter.status');
        if ($rawStatus) {
            $statuses = array_filter(array_map('trim', explode(',', $rawStatus)));
            if (count($statuses) === 1) {
                $query->where('status', $statuses[0]);
            } elseif (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            }
        }

        if ($request->filled('fulfillment_mode')) {
            $query->where('fulfillment_mode', $request->input('fulfillment_mode'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->input('team_id'));
        }
        if ($request->filled('lead_technician_id')) {
            $query->where('lead_technician_id', $request->input('lead_technician_id'));
        }
        if ($request->filled('workshop_unit_id')) {
            $query->where('workshop_unit_id', $request->input('workshop_unit_id'));
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('planned_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('planned_date', '<=', $request->input('date_to'));
        }

        $q = $request->input('q') ?? $request->input('search');
        if (!empty($q)) {
            $query->where(function ($sub) use ($q) {
                $sub->where('swo_number', 'LIKE', "%{$q}%")
                    ->orWhereHas('client', fn ($c) => $c->where('legal_name', 'LIKE', "%{$q}%")->orWhere('commercial_name', 'LIKE', "%{$q}%"))
                    ->orWhereHas('salesOrder', fn ($s) => $s->where('reference', 'LIKE', "%{$q}%"));
            });
        }

        // Sorting
        $sortBy = $request->input('sortBy', 'created_at');
        $orderBy = $request->input('orderBy', 'desc');
        $query->orderBy($sortBy, $orderBy);

        $perPage = (int) $request->input('itemsPerPage', $request->input('per_page', 15));
        $swos = $query->paginate($perPage);

        return ServiceWorkOrderListResource::collection($swos);
    }

    /**
     * Grouped kanban board feed.
     */
    public function kanban(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceWorkOrder::class);

        $columns = [
            'draft'               => SwoStatus::Draft,
            'planned'             => SwoStatus::Planned,
            'scheduled'           => SwoStatus::Scheduled,
            'in_progress'         => SwoStatus::InProgress,
            'partially_completed' => SwoStatus::PartiallyCompleted,
            'completed'           => SwoStatus::Completed,
            'closed'              => SwoStatus::Closed,
        ];

        $board = [];

        foreach ($columns as $key => $status) {
            $items = ServiceWorkOrder::query()
                ->where('status', $status->value)
                ->with(['client', 'team', 'leadTechnician', 'workshopUnit', 'salesOrder'])
                ->withCount(['items', 'missions'])
                ->latest()
                ->limit(30)
                ->get();

            $board[$key] = ServiceWorkOrderListResource::collection($items);
        }

        return response()->json(['data' => $board]);
    }

    /**
     * Get eligible service lines for a Sales Order.
     */
    public function eligibleSoLines(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('create', ServiceWorkOrder::class);

        $lines = $this->swoService->getEligibleSoLines($salesOrder);

        return response()->json(['data' => $lines]);
    }

    /**
     * Create SWO from Sales Order.
     */
    public function createFromSo(CreateSwoFromSoRequest $request): JsonResponse
    {
        $this->authorize('create', ServiceWorkOrder::class);

        $so = SalesOrder::findOrFail($request->input('sales_order_id'));

        $swo = $this->swoService->createFromSalesOrder(
            $so,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Service Work Order created successfully.',
            'data'    => new ServiceWorkOrderResource($swo->load(['items', 'client', 'salesOrder', 'workshopUnit'])),
        ], 201);
    }

    /**
     * Show SWO detail.
     */
    public function show(ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('view', $serviceWorkOrder);

        $serviceWorkOrder->load([
            'client',
            'serviceSite',
            'team',
            'leadTechnician',
            'workshopUnit',
            'vehicle',
            'salesOrder',
            'items.catalogItem',
            'missions.interventions',
            'statusHistories.changer',
        ]);

        return response()->json([
            'data' => new ServiceWorkOrderResource($serviceWorkOrder),
        ]);
    }

    /**
     * Update SWO.
     */
    public function update(UpdateSwoRequest $request, ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('update', $serviceWorkOrder);

        $swo = $this->swoService->scheduleOrPlan($serviceWorkOrder, $request->validated(), $request->user());

        return response()->json([
            'message' => 'Service Work Order updated successfully.',
            'data'    => new ServiceWorkOrderResource($swo->load(['client', 'team', 'leadTechnician', 'workshopUnit', 'vehicle'])),
        ]);
    }

    /**
     * Transition SWO status.
     */
    public function transition(TransitionSwoRequest $request, ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('transition', $serviceWorkOrder);

        $targetStatus = SwoStatus::from($request->input('status'));

        $swo = $this->swoService->transition(
            $serviceWorkOrder,
            $targetStatus,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => "SWO transitioned to {$targetStatus->label()}.",
            'data'    => new ServiceWorkOrderResource($swo),
        ]);
    }

    /**
     * Delete SWO (soft delete).
     */
    public function destroy(ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('delete', $serviceWorkOrder);

        if (!in_array($serviceWorkOrder->status, [SwoStatus::Draft, SwoStatus::Cancelled], true)) {
            return response()->json([
                'message' => 'Only Draft or Cancelled Service Work Orders can be deleted.',
            ], 422);
        }

        $serviceWorkOrder->delete();

        return response()->json([
            'message' => 'Service Work Order deleted successfully.',
        ]);
    }

    /**
     * Get missions for an SWO.
     */
    public function missions(ServiceWorkOrder $serviceWorkOrder): AnonymousResourceCollection
    {
        $this->authorize('view', $serviceWorkOrder);

        $missions = $serviceWorkOrder->missions()
            ->with(['team', 'leadTechnician', 'workshopUnit', 'serviceSite'])
            ->withCount('interventions')
            ->latest()
            ->get();

        return MissionListResource::collection($missions);
    }

    /**
     * Get operational summary statistics for SWO Tracker page.
     */
    public function stats(ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('view', $serviceWorkOrder);
        $stats = $this->swoService->getSwoStats($serviceWorkOrder);
        return response()->json(['data' => $stats]);
    }

    /**
     * Operational Scope / Equipment Explorer feed for the SWO tracker.
     */
    public function scope(ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('view', $serviceWorkOrder);

        return response()->json([
            'data' => $this->swoService->getOperationalScope($serviceWorkOrder),
        ]);
    }

    /**
     * Get locations covered by SWO.
     */
    public function locations(ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('view', $serviceWorkOrder);
        $locations = $serviceWorkOrder->locations()->with('clientSite')->get();
        return response()->json(['data' => $locations]);
    }

    /**
     * Update/sync locations covered by SWO.
     */
    public function updateLocations(Request $request, ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('update', $serviceWorkOrder);
        $siteIds = $request->input('site_ids', []);
        $this->swoService->attachLocations($serviceWorkOrder, $siteIds);
        return response()->json([
            'message' => 'Locations updated successfully.',
            'data'    => $serviceWorkOrder->locations()->with('clientSite')->get(),
        ]);
    }

    /**
     * Get technicians assigned to SWO.
     */
    public function employees(ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('view', $serviceWorkOrder);
        $employees = $serviceWorkOrder->swoEmployees()->with('employee')->get();
        return response()->json(['data' => $employees]);
    }

    /**
     * Update/sync technicians assigned to SWO.
     */
    public function updateEmployees(Request $request, ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('update', $serviceWorkOrder);
        $employeeIds = $request->input('employee_ids', []);
        $leadId = $request->input('lead_employee_id');
        $this->swoService->attachEmployees($serviceWorkOrder, $employeeIds, $leadId);
        return response()->json([
            'message' => 'Technicians updated successfully.',
            'data'    => $serviceWorkOrder->swoEmployees()->with('employee')->get(),
        ]);
    }

    /**
     * Get operational costs for SWO.
     */
    public function operationalCosts(ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('view', $serviceWorkOrder);
        $costs = $serviceWorkOrder->operationalCosts()->latest()->get();
        return response()->json(['data' => $costs]);
    }

    /**
     * Record an operational cost entry.
     */
    public function storeOperationalCost(Request $request, ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('update', $serviceWorkOrder);
        $validated = $request->validate([
            'cost_type'      => ['required', 'string'],
            'description'    => ['nullable', 'string', 'max:255'],
            'planned_amount' => ['nullable', 'numeric', 'min:0'],
            'actual_amount'  => ['nullable', 'numeric', 'min:0'],
            'currency'       => ['nullable', 'string', 'max:3'],
            'notes'          => ['nullable', 'string'],
        ]);

        $cost = $serviceWorkOrder->operationalCosts()->create([
            ...$validated,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Operational cost recorded successfully.',
            'data'    => $cost,
        ], 201);
    }

    /**
     * Delete an operational cost entry.
     */
    public function destroyOperationalCost(ServiceWorkOrder $serviceWorkOrder, \App\Modules\OperationalHub\Models\SwoOperationalCost $cost): JsonResponse
    {
        $this->authorize('update', $serviceWorkOrder);
        if ($cost->swo_id !== $serviceWorkOrder->id) {
            abort(404);
        }
        $cost->delete();
        return response()->json(['message' => 'Cost entry removed.']);
    }
}
