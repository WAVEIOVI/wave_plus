<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Controllers\Concerns\AuthorizesInventoryRequests;
use App\Modules\Inventory\Models\WarehouseZone;
use App\Modules\Inventory\Requests\StoreWarehouseZoneRequest;
use App\Modules\Inventory\Requests\UpdateWarehouseZoneRequest;
use App\Modules\Inventory\Resources\WarehouseZoneResource;
use App\Modules\Inventory\Services\InventoryQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseZoneController extends Controller
{
    use AuthorizesInventoryRequests;

    public function __construct(
        private readonly InventoryQueryService $queryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WarehouseZone::class);
        $query = WarehouseZone::with('site')
            ->withCount('locations');

        // Filter by site
        if ($siteId = $request->input('filter.site_id') ?? $request->route('site')) {
            $query->where('site_id', $siteId);
        }

        // Filter by zone type
        if ($zoneType = $request->input('filter.zone_type')) {
            $query->where('zone_type', $zoneType);
        }

        // Filter by status
        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%'.$search.'%');
                $this->queryService->applyLocalizedNameSearch($q, 'name', $search);
            });
        }

        return WarehouseZoneResource::collection(
            $query->orderBy('code')->paginate($this->queryService->cappedPerPage($request))
        );
    }

    /**
     * Store a new warehouse zone.
     */
    public function store(StoreWarehouseZoneRequest $request): JsonResponse
    {
        $this->authorize('create', WarehouseZone::class);
        $validated = $request->validated();
        $validated['status'] = $validated['status'] ?? 'active';

        $zone = WarehouseZone::create($validated);
        $zone->load('site');
        $zone->loadCount('locations');

        return response()->json([
            'message' => 'Warehouse zone created successfully.',
            'data' => new WarehouseZoneResource($zone),
        ], 201);
    }

    /**
     * Show a single zone with its locations.
     */
    public function show(string $id): WarehouseZoneResource
    {
        $zone = WarehouseZone::with(['site', 'locations'])
            ->withCount('locations')
            ->findOrFail($id);

        $this->authorize('view', $zone);

        return new WarehouseZoneResource($zone);
    }

    /**
     * Update a warehouse zone.
     */
    public function update(UpdateWarehouseZoneRequest $request, string $id): JsonResponse
    {
        $zone = WarehouseZone::findOrFail($id);
        $this->authorize('update', $zone);
        $zone->update($request->validated());
        $zone->load('site');
        $zone->loadCount('locations');

        return response()->json([
            'message' => 'Warehouse zone updated successfully.',
            'data' => new WarehouseZoneResource($zone),
        ]);
    }

    /**
     * Soft delete a warehouse zone.
     */
    public function destroy(string $id): JsonResponse
    {
        $zone = WarehouseZone::findOrFail($id);
        $this->authorize('delete', $zone);

        // Block if locations have stock
        $hasStock = $zone->locations()
            ->whereHas('stockItems', fn ($q) => $q->where('qty_on_hand', '>', 0))
            ->exists();

        if ($hasStock) {
            return response()->json([
                'message' => 'Cannot delete zone: it has locations with active stock.',
            ], 422);
        }

        $zone->delete();

        return response()->json([
            'message' => 'Warehouse zone deleted successfully.',
        ]);
    }
}
