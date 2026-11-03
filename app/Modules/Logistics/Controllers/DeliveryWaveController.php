<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logistics\Controllers\Concerns\AuthorizesLogisticsRequests;
use App\Modules\Logistics\Enums\DeliveryWaveStatus;
use App\Modules\Logistics\Models\DeliveryWave;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Resources\DeliveryWaveResource;
use App\Modules\Logistics\Services\DeliveryWaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryWaveController extends Controller
{
    use AuthorizesLogisticsRequests;

    public function __construct(
        private readonly DeliveryWaveService $waveService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DeliveryWave::class);

        $query = DeliveryWave::query()
            ->with(['warehouse', 'vehicle', 'approvedDriver', 'stops.deliveryAddress', 'stops.deliveryWorkOrder.client']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('planned_date')) {
            $query->whereDate('planned_date', $request->input('planned_date'));
        }
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }
        if ($request->filled('approved_driver_id')) {
            $query->where('approved_driver_id', $request->input('approved_driver_id'));
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }

        $perPage = (int) $request->input('itemsPerPage', $request->input('per_page', 15));
        $waves = $query->latest('planned_date')->paginate($perPage);

        return DeliveryWaveResource::collection($waves);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', DeliveryWave::class);

        $validated = $request->validate([
            'planned_date' => 'required|date',
            'dispatch_time' => 'nullable|string',
            'warehouse_id' => 'nullable|ulid|exists:warehouse_sites,id',
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
            'approved_driver_id' => 'nullable|ulid|exists:hr_approved_drivers,id',
            'route_notes' => 'nullable|string|max:2000',
            'dwo_ids' => 'nullable|array',
            'dwo_ids.*' => 'ulid|exists:delivery_work_orders,id',
        ]);

        $wave = $this->waveService->createWave($validated, $request->user());

        return (new DeliveryWaveResource($wave))->response()->setStatusCode(201);
    }

    public function show(DeliveryWave $deliveryWave): DeliveryWaveResource
    {
        $this->authorize('view', $deliveryWave);

        $deliveryWave->loadMissing([
            'warehouse',
            'vehicle',
            'approvedDriver',
            'stops.deliveryAddress',
            'stops.deliveryWorkOrder.client',
            'stops.deliveryWorkOrder.lines',
        ]);

        return new DeliveryWaveResource($deliveryWave);
    }

    public function update(Request $request, DeliveryWave $deliveryWave): DeliveryWaveResource
    {
        $this->authorize('update', $deliveryWave);

        $validated = $request->validate([
            'planned_date' => 'nullable|date',
            'dispatch_time' => 'nullable|string',
            'warehouse_id' => 'nullable|ulid|exists:warehouse_sites,id',
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
            'approved_driver_id' => 'nullable|ulid|exists:hr_approved_drivers,id',
            'route_notes' => 'nullable|string|max:2000',
        ]);

        $validated['updated_by'] = $request->user()->id;
        $deliveryWave->update($validated);

        return new DeliveryWaveResource($deliveryWave->fresh(['warehouse', 'vehicle', 'approvedDriver', 'stops.deliveryAddress']));
    }

    public function destroy(DeliveryWave $deliveryWave): JsonResponse
    {
        $this->authorize('delete', $deliveryWave);

        if ($deliveryWave->status !== DeliveryWaveStatus::Draft && $deliveryWave->status !== DeliveryWaveStatus::Cancelled) {
            return response()->json(['error' => 'Only Draft or Cancelled waves can be deleted.'], 422);
        }

        $deliveryWave->delete();

        return response()->json(null, 204);
    }

    public function addDwo(Request $request, DeliveryWave $deliveryWave): JsonResponse
    {
        $this->authorize('update', $deliveryWave);

        $validated = $request->validate([
            'dwo_id' => 'required|ulid|exists:delivery_work_orders,id',
            'sequence' => 'nullable|integer|min:1',
        ]);

        $dwo = DeliveryWorkOrder::findOrFail($validated['dwo_id']);
        $this->waveService->addDwoToWave($deliveryWave, $dwo, $validated['sequence'] ?? null);

        return (new DeliveryWaveResource($deliveryWave->fresh(['stops.deliveryAddress', 'stops.deliveryWorkOrder.client'])))->response();
    }

    public function removeDwo(DeliveryWave $deliveryWave, DeliveryWorkOrder $deliveryWorkOrder): JsonResponse
    {
        $this->authorize('update', $deliveryWave);

        $this->waveService->removeDwoFromWave($deliveryWave, $deliveryWorkOrder);

        return (new DeliveryWaveResource($deliveryWave->fresh(['stops.deliveryAddress', 'stops.deliveryWorkOrder.client'])))->response();
    }

    public function reorderStops(Request $request, DeliveryWave $deliveryWave): JsonResponse
    {
        $this->authorize('update', $deliveryWave);

        $validated = $request->validate([
            'stops' => 'required|array',
            'stops.*.stop_id' => 'required|ulid|exists:delivery_stops,id',
            'stops.*.sequence' => 'required|integer|min:1',
        ]);

        $this->waveService->reorderStops($deliveryWave, $validated['stops']);

        return (new DeliveryWaveResource($deliveryWave->fresh(['stops.deliveryAddress', 'stops.deliveryWorkOrder.client'])))->response();
    }

    public function dispatch(Request $request, DeliveryWave $deliveryWave): JsonResponse
    {
        $this->authorize('dispatch', $deliveryWave);

        $wave = $this->waveService->dispatchWave($deliveryWave, $request->user());

        return (new DeliveryWaveResource($wave))->response();
    }

    public function complete(Request $request, DeliveryWave $deliveryWave): JsonResponse
    {
        $this->authorize('complete', $deliveryWave);

        $wave = $this->waveService->completeWave($deliveryWave, $request->user());

        return (new DeliveryWaveResource($wave))->response();
    }
}
