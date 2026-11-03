<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Controllers\Concerns\AuthorizesInventoryRequests;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Services\InventoryQueryService;
use App\Modules\Inventory\Requests\StoreWarehouseLocationRequest;
use App\Modules\Inventory\Requests\UpdateWarehouseLocationRequest;
use App\Modules\Inventory\Resources\WarehouseLocationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseLocationController extends Controller
{
    use AuthorizesInventoryRequests;

    public function __construct(
        private readonly InventoryQueryService $queryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WarehouseLocation::class);

        $query = WarehouseLocation::with(['zone.site']);

        // Filter by zone
        if ($zoneId = $request->input('filter.zone_id') ?? $request->route('zone')) {
            $query->where('zone_id', $zoneId);
        }

        // Filter by site (through zone)
        if ($siteId = $request->input('filter.site_id')) {
            $query->whereHas('zone', fn ($q) => $q->where('site_id', $siteId));
        }

        // Filter by location type
        if ($locationType = $request->input('filter.location_type')) {
            $query->where('location_type', $locationType);
        }

        // Filter by active status
        if ($request->has('filter.is_active')) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        // Search by barcode
        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('barcode', 'like', "%{$search}%")
                    ->orWhere('aisle', 'like', "%{$search}%")
                    ->orWhere('rack', 'like', "%{$search}%");
            });
        }

        return WarehouseLocationResource::collection(
            $query->orderBy('barcode')->paginate($this->queryService->cappedPerPage($request))
        );
    }

    /**
     * Store a new warehouse location.
     */
    public function store(StoreWarehouseLocationRequest $request): JsonResponse
    {
        $this->authorize('create', WarehouseLocation::class);
        $validated = $request->validated();
        $validated['is_active'] = $validated['is_active'] ?? true;

        $location = WarehouseLocation::create($validated);
        $location->load('zone.site');

        return response()->json([
            'message' => 'Warehouse location created successfully.',
            'data' => new WarehouseLocationResource($location),
        ], 201);
    }

    /**
     * Show a single location.
     */
    public function show(string $id): WarehouseLocationResource
    {
        $location = WarehouseLocation::with(['zone.site', 'stockItems'])
            ->findOrFail($id);

        $this->authorize('view', $location);

        return new WarehouseLocationResource($location);
    }

    /**
     * Update a warehouse location.
     */
    public function update(UpdateWarehouseLocationRequest $request, string $id): JsonResponse
    {
        $location = WarehouseLocation::findOrFail($id);
        $this->authorize('update', $location);
        $location->update($request->validated());
        $location->load('zone.site');

        return response()->json([
            'message' => 'Warehouse location updated successfully.',
            'data' => new WarehouseLocationResource($location),
        ]);
    }

    /**
     * Soft delete a warehouse location.
     */
    public function destroy(string $id): JsonResponse
    {
        $location = WarehouseLocation::findOrFail($id);
        $this->authorize('delete', $location);

        $hasStock = $location->stockItems()
            ->where('qty_on_hand', '>', 0)
            ->exists();

        if ($hasStock) {
            return response()->json([
                'message' => 'Cannot delete location: it has active stock items.',
            ], 422);
        }

        $location->delete();

        return response()->json([
            'message' => 'Warehouse location deleted successfully.',
        ]);
    }

    /**
     * Toggle the active status of a location.
     */
    public function toggleActive(string $id): JsonResponse
    {
        $location = WarehouseLocation::findOrFail($id);
        $this->authorize('update', $location);
        $location->is_active = ! $location->is_active;
        $location->save();
        $location->load('zone.site');

        return response()->json([
            'message' => $location->is_active
                ? 'Location activated successfully.'
                : 'Location deactivated successfully.',
            'data' => new WarehouseLocationResource($location),
        ]);
    }
}
