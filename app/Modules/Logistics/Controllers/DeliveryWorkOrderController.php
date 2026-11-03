<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Logistics\Controllers\Concerns\AuthorizesLogisticsRequests;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\DwoLine;
use App\Modules\Logistics\Requests\CreateDwoFromSoRequest;
use App\Modules\Logistics\Requests\UpdateDwoRequest;
use App\Modules\Logistics\Resources\DeliveryWorkOrderListResource;
use App\Modules\Logistics\Resources\DeliveryWorkOrderResource;
use App\Modules\Logistics\Resources\DwoLineResource;
use App\Modules\Logistics\Services\DwoCreationService;
use App\Modules\Logistics\Services\DwoPickingService;
use App\Modules\Logistics\Services\DWOService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryWorkOrderController extends Controller
{
    use AuthorizesLogisticsRequests;

    public function __construct(
        private readonly DWOService $dwoService,
        private readonly DwoCreationService $creationService,
        private readonly DwoPickingService $pickingService,
    ) {}

    /**
     * Dashboard / Kanban metadata counters.
     */
    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', DeliveryWorkOrder::class);

        return response()->json([
            'data' => [
                'total_dwos' => DeliveryWorkOrder::count(),
                'draft' => DeliveryWorkOrder::where('status', DwoStatus::Draft->value)->count(),
                'planned' => DeliveryWorkOrder::where('status', DwoStatus::Planned->value)->count(),
                'ready_to_pick' => DeliveryWorkOrder::where('status', DwoStatus::ReadyToPick->value)->count(),
                'picking' => DeliveryWorkOrder::where('status', DwoStatus::Picking->value)->count(),
                'ready_to_load' => DeliveryWorkOrder::where('status', DwoStatus::ReadyToLoad->value)->count(),
                'loaded' => DeliveryWorkOrder::where('status', DwoStatus::Loaded->value)->count(),
                'dispatched' => DeliveryWorkOrder::where('status', DwoStatus::Dispatched->value)->count(),
                'in_transit' => DeliveryWorkOrder::where('status', DwoStatus::InTransit->value)->count(),
                'delivered' => DeliveryWorkOrder::where('status', DwoStatus::Delivered->value)->count(),
                'partially_delivered' => DeliveryWorkOrder::where('status', DwoStatus::PartiallyDelivered->value)->count(),
                'delivery_failed' => DeliveryWorkOrder::where('status', DwoStatus::DeliveryFailed->value)->count(),
                'exception' => DeliveryWorkOrder::where('status', DwoStatus::Exception->value)->count(),
                'closed' => DeliveryWorkOrder::where('status', DwoStatus::Closed->value)->count(),
                'cancelled' => DeliveryWorkOrder::where('status', DwoStatus::Cancelled->value)->count(),
            ],
        ]);
    }

    /**
     * Paginated index with rich filtering.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DeliveryWorkOrder::class);

        $query = DeliveryWorkOrder::query()
            ->with(['client', 'driver', 'vehicle', 'deliveryAddress', 'warehouse', 'salesOrder', 'wave'])
            ->withCount('lines');

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

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        } elseif ($request->filled('filter.priority')) {
            $query->where('priority', $request->input('filter.priority'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }
        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }
        if ($request->filled('wave_id')) {
            $query->where('wave_id', $request->input('wave_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('dwo_number', 'LIKE', "%{$q}%")
                    ->orWhereHas('client', fn ($c) => $c->where('legal_name', 'LIKE', "%{$q}%")->orWhere('commercial_name', 'LIKE', "%{$q}%"))
                    ->orWhereHas('salesOrder', fn ($s) => $s->where('reference', 'LIKE', "%{$q}%"));
            });
        }

        // Sorting
        $sortBy = $request->input('sortBy', 'created_at');
        $orderBy = $request->input('orderBy', 'desc');
        $query->orderBy($sortBy, $orderBy);

        $perPage = (int) $request->input('itemsPerPage', $request->input('per_page', 15));
        $dwos = $query->paginate($perPage);

        return DeliveryWorkOrderListResource::collection($dwos);
    }

    /**
     * Grouped kanban board feed.
     */
    public function kanban(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DeliveryWorkOrder::class);

        $columns = [
            'draft' => DwoStatus::Draft,
            'planned' => DwoStatus::Planned,
            'ready_to_pick' => DwoStatus::ReadyToPick,
            'picking' => DwoStatus::Picking,
            'ready_to_load' => DwoStatus::ReadyToLoad,
            'loaded' => DwoStatus::Loaded,
            'dispatched' => DwoStatus::Dispatched,
            'in_transit' => DwoStatus::InTransit,
            'delivered' => DwoStatus::Delivered,
            'exception' => DwoStatus::Exception,
            'closed' => DwoStatus::Closed,
        ];

        $board = [];

        foreach ($columns as $key => $status) {
            $items = DeliveryWorkOrder::query()
                ->where('status', $status->value)
                ->with(['client', 'driver', 'vehicle', 'warehouse', 'salesOrder'])
                ->withCount('lines')
                ->latest()
                ->limit(30)
                ->get();

            $board[$key] = [
                'status' => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'color' => $status->badgeColor(),
                ],
                'count' => DeliveryWorkOrder::where('status', $status->value)->count(),
                'items' => DeliveryWorkOrderListResource::collection($items),
            ];
        }

        return response()->json(['data' => $board]);
    }

    /**
     * Get allocatable remaining items from a Sales Order.
     */
    public function eligibleSoLines(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('create', DeliveryWorkOrder::class);

        $lines = $this->creationService->getEligibleSalesOrderLines($salesOrder);

        return response()->json([
            'data' => [
                'sales_order_id' => $salesOrder->id,
                'sales_order_reference' => $salesOrder->reference,
                'client_id' => $salesOrder->client_id,
                'client_name' => $salesOrder->client?->legal_name,
                'status' => $salesOrder->status?->value ?? (string) $salesOrder->status,
                'lines' => $lines,
            ],
        ]);
    }

    /**
     * Create DWO from Sales Order (The authoritative creation path).
     */
    public function createFromSo(CreateDwoFromSoRequest $request): JsonResponse
    {
        $this->authorize('create', DeliveryWorkOrder::class);

        $so = SalesOrder::findOrFail($request->input('sales_order_id'));
        $dwo = $this->creationService->createFromSalesOrder($so, $request->validated(), $request->user());

        return (new DeliveryWorkOrderResource($dwo))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display a specific Delivery Work Order with full details.
     */
    public function show(DeliveryWorkOrder $deliveryWorkOrder): DeliveryWorkOrderResource
    {
        $this->authorize('view', $deliveryWorkOrder);

        $deliveryWorkOrder->loadMissing([
            'lines.catalogItem.uom',
            'lines.catalogVariant',
            'lines.warehouseLocation',
            'pod.creator',
            'exceptions.resolver',
            'statusHistories.user',
            'client',
            'warehouse',
            'deliveryAddress',
            'deliverySite',
            'driver',
            'vehicle',
            'salesOrder',
            'wave',
            'deliveryNote',
            'reservations.catalogItem',
        ]);

        return new DeliveryWorkOrderResource($deliveryWorkOrder);
    }

    /**
     * Update editable DWO fields.
     */
    public function update(UpdateDwoRequest $request, DeliveryWorkOrder $deliveryWorkOrder): DeliveryWorkOrderResource
    {
        $this->authorize('update', $deliveryWorkOrder);

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $deliveryWorkOrder->update($data);

        return new DeliveryWorkOrderResource($deliveryWorkOrder->fresh(['client', 'warehouse', 'deliveryAddress', 'driver', 'vehicle']));
    }

    /**
     * Delete a draft DWO.
     */
    public function destroy(DeliveryWorkOrder $deliveryWorkOrder): JsonResponse
    {
        $this->authorize('delete', $deliveryWorkOrder);

        if ($deliveryWorkOrder->status !== DwoStatus::Draft && $deliveryWorkOrder->status !== DwoStatus::Cancelled) {
            return response()->json([
                'error' => 'Only Draft or Cancelled delivery work orders can be deleted.',
            ], 422);
        }

        $deliveryWorkOrder->delete();

        return response()->json(null, 204);
    }

    /**
     * Unified state transition endpoint.
     */
    public function transition(Request $request, DeliveryWorkOrder $deliveryWorkOrder): JsonResponse
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        $policyMethod = match ($request->input('status')) {
            'planned' => 'plan',
            'ready_to_pick', 'picking' => 'pick',
            'ready_to_load', 'loaded' => 'load',
            'dispatched', 'in_transit' => 'dispatch',
            'delivered', 'partially_delivered', 'delivery_failed' => 'deliver',
            'closed' => 'close',
            'cancelled' => 'cancel',
            default => 'update',
        };

        $this->authorize($policyMethod, $deliveryWorkOrder);

        $payload = $request->except(['status']);
        $dwo = $this->dwoService->transition($deliveryWorkOrder, $request->input('status'), $request->user(), $payload);

        return (new DeliveryWorkOrderResource($dwo))->response();
    }

    /**
     * Record picking for an individual line.
     */
    public function recordPick(Request $request, DeliveryWorkOrder $deliveryWorkOrder, DwoLine $line): JsonResponse
    {
        $this->authorize('pick', $deliveryWorkOrder);

        $validated = $request->validate([
            'qty_picked' => 'required|numeric|min:0',
            'lot_number' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'warehouse_location_id' => 'nullable|ulid|exists:warehouse_locations,id',
            'driver_notes' => 'nullable|string|max:1000',
        ]);

        if ($line->dwo_id !== $deliveryWorkOrder->id) {
            return response()->json(['error' => 'Line does not belong to this DWO.'], 422);
        }

        $updatedLine = $this->pickingService->recordPick($line, $validated, $request->user()->id);

        return (new DwoLineResource($updatedLine))->response();
    }

    /**
     * Batch confirm picks for DWO.
     */
    public function batchConfirmPicks(Request $request, DeliveryWorkOrder $deliveryWorkOrder): JsonResponse
    {
        $this->authorize('pick', $deliveryWorkOrder);

        $request->validate([
            'picks' => 'required|array|min:1',
            'picks.*.line_id' => 'required|exists:dwo_lines,id',
            'picks.*.qty_picked' => 'required|numeric|min:0',
            'picks.*.lot_number' => 'nullable|string|max:100',
            'picks.*.serial_number' => 'nullable|string|max:100',
            'picks.*.warehouse_location_id' => 'nullable|exists:warehouse_locations,id',
        ]);

        $this->pickingService->batchRecordPicks($deliveryWorkOrder, $request->input('picks'), $request->user()->id);

        return (new DeliveryWorkOrderResource($deliveryWorkOrder->fresh(['lines.catalogItem'])))->response();
    }

    /**
     * Sales-approve a proposed catalog/variant substitution on a DWO line.
     * Does not mutate the original Sales Order line.
     */
    public function approveSubstitution(Request $request, DeliveryWorkOrder $deliveryWorkOrder, DwoLine $line): JsonResponse
    {
        $this->authorize('update', $deliveryWorkOrder);

        if ($line->dwo_id !== $deliveryWorkOrder->id) {
            abort(404);
        }

        $validated = $request->validate([
            'approved' => 'required|boolean',
            'reason' => 'nullable|string|max:2000',
        ]);

        if ($line->substitution_status !== 'proposed') {
            return response()->json(['error' => 'This line has no pending substitution proposal.'], 422);
        }

        if ($validated['approved']) {
            $line->substitution_status = 'approved';
            $line->substitution_approved_by = $request->user()->id;
            $line->substitution_approved_at = now();
            if (! empty($validated['reason'])) {
                $line->substitution_reason = $validated['reason'];
            }
        } else {
            // Revert operational item to commercial item
            $line->catalog_item_id = $line->commercial_catalog_item_id ?? $line->catalog_item_id;
            $line->catalog_variant_id = $line->commercial_catalog_variant_id;
            $line->substitution_status = 'rejected';
            $line->substitution_approved_by = $request->user()->id;
            $line->substitution_approved_at = now();
        }
        $line->updated_by = $request->user()->id;
        $line->save();

        return (new DeliveryWorkOrderResource($deliveryWorkOrder->fresh(['lines.catalogItem', 'lines.catalogVariant'])))->response();
    }

    /**
     * Generate Delivery Note for DWO.
     */
    public function generateDeliveryNote(Request $request, DeliveryWorkOrder $deliveryWorkOrder): JsonResponse
    {
        $this->authorize('update', $deliveryWorkOrder);

        $deliveryNote = $this->dwoService->ensureDeliveryNoteCreated($deliveryWorkOrder, $request->user());

        return response()->json([
            'message' => 'Delivery Note generated successfully.',
            'delivery_note' => $deliveryNote,
        ]);
    }
}
