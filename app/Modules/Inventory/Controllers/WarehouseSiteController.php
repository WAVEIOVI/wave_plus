<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Controllers\Concerns\AuthorizesInventoryRequests;
use App\Modules\Inventory\Models\MobileWarehouseAssignment;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Inventory\Requests\LinkMobileWarehouseRequest;
use App\Modules\Inventory\Requests\StoreWarehouseSiteRequest;
use App\Modules\Inventory\Requests\UpdateWarehouseSiteRequest;
use App\Modules\Inventory\Resources\MobileWarehouseAssignmentResource;
use App\Modules\Inventory\Resources\WarehouseSiteResource;
use App\Modules\Inventory\Services\InventoryQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WarehouseSiteController extends Controller
{
    use AuthorizesInventoryRequests;

    public function __construct(
        private readonly InventoryQueryService $queryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WarehouseSite::class);
        $query = WarehouseSite::withCount(['zones', 'locations'])
            ->with(['manager', 'vehicle', 'currentAssignment.vehicle']);

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%'.$search.'%');
                $this->queryService->applyLocalizedNameSearch($q, 'name', $search);
            });
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        if ($siteType = $request->input('filter.site_type')) {
            $query->where('site_type', $siteType);
        }

        // ── Sorting ────────────────────────────────────────────────
        $sortBy = $request->input('sort_by', $request->input('sortBy', 'code'));
        $orderBy = $request->input('sort_order', $request->input('orderBy', 'asc'));

        $allowedSorts = ['code', 'name', 'site_type', 'status', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $orderBy === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('code');
        }

        return WarehouseSiteResource::collection(
            $query->paginate($this->queryService->cappedPerPage($request))
        );
    }

    /**
     * Store a new warehouse site.
     */
    public function store(StoreWarehouseSiteRequest $request): JsonResponse
    {
        $this->authorize('create', WarehouseSite::class);
        $validated = $request->validated();
        $validated['created_by'] = Auth::id();
        $validated['status'] = $validated['status'] ?? 'active';

        $site = WarehouseSite::create($validated);
        $site->load('manager');
        $site->loadCount(['zones', 'locations']);

        return response()->json([
            'message' => 'Warehouse site created successfully.',
            'data' => new WarehouseSiteResource($site),
        ], 201);
    }

    /**
     * Show a single warehouse site with zones and locations.
     */
    public function show(string $id): WarehouseSiteResource
    {
        $site = WarehouseSite::with(['manager', 'vehicle', 'currentAssignment.vehicle', 'zones.locations'])
            ->withCount(['zones', 'locations'])
            ->findOrFail($id);

        $this->authorize('view', $site);

        return new WarehouseSiteResource($site);
    }

    /**
     * Update a warehouse site.
     */
    public function update(UpdateWarehouseSiteRequest $request, string $id): JsonResponse
    {
        $site = WarehouseSite::findOrFail($id);
        $this->authorize('update', $site);
        $validated = $request->validated();
        $validated['updated_by'] = Auth::id();

        $site->update($validated);
        $site->load('manager');
        $site->loadCount(['zones', 'locations']);

        return response()->json([
            'message' => 'Warehouse site updated successfully.',
            'data' => new WarehouseSiteResource($site),
        ]);
    }

    /**
     * Soft delete a warehouse site.
     * Blocked if site has active stock items.
     */
    public function destroy(string $id): JsonResponse
    {
        $site = WarehouseSite::findOrFail($id);
        $this->authorize('delete', $site);

        // Block deletion if any locations under this site have stock items
        $hasStock = $site->locations()
            ->whereHas('stockItems', function ($q) {
                $q->where('qty_on_hand', '>', 0);
            })
            ->exists();

        if ($hasStock) {
            return response()->json([
                'message' => 'Cannot delete site: it has locations with active stock.',
            ], 422);
        }

        $site->delete();

        return response()->json([
            'message' => 'Warehouse site deleted successfully.',
        ]);
    }

    /**
     * Link a vehicle to a mobile warehouse site.
     */
    public function linkVehicle(LinkMobileWarehouseRequest $request, string $id): JsonResponse
    {
        $site = WarehouseSite::findOrFail($id);
        $this->authorize('update', $site);

        if ($site->site_type !== 'mobile') {
            return response()->json([
                'message' => 'Only mobile warehouse sites can be linked to vehicles.',
            ], 422);
        }

        $validated = $request->validated();
        $vehicleId = $validated['vehicle_id'];

        return DB::transaction(function () use ($site, $validated, $vehicleId) {
            // Close any existing current assignment
            $site->currentAssignment()->update(['is_current' => false]);

            // Create new assignment
            MobileWarehouseAssignment::create([
                'warehouse_site_id' => $site->id,
                'vehicle_id' => $vehicleId,
                'assigned_from' => $validated['assigned_from'],
                'assigned_until' => $validated['assigned_until'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'is_current' => true,
                'created_by' => Auth::id(),
            ]);

            // Update the site's vehicle_id for quick reference
            $site->vehicle_id = $vehicleId;
            $site->updated_by = Auth::id();
            $site->save();

            $site->load('manager', 'vehicle', 'currentAssignment.vehicle');
            $site->loadCount(['zones', 'locations']);

            return response()->json([
                'message' => 'Vehicle linked to mobile warehouse successfully.',
                'data' => new WarehouseSiteResource($site),
            ]);
        });
    }

    /**
     * Unlink vehicle from a mobile warehouse site.
     */
    public function unlinkVehicle(Request $request, string $id): JsonResponse
    {
        $site = WarehouseSite::findOrFail($id);
        $this->authorize('update', $site);

        if ($site->site_type !== 'mobile') {
            return response()->json([
                'message' => 'Only mobile warehouse sites can have vehicles unlinked.',
            ], 422);
        }

        return DB::transaction(function () use ($site) {
            // Close current assignment
            $site->currentAssignment()->update(['is_current' => false]);

            // Clear the site's vehicle_id
            $site->vehicle_id = null;
            $site->updated_by = Auth::id();
            $site->save();

            $site->load('manager');
            $site->loadCount(['zones', 'locations']);

            return response()->json([
                'message' => 'Vehicle unlinked from mobile warehouse successfully.',
                'data' => new WarehouseSiteResource($site),
            ]);
        });
    }

    /**
     * Get assignment history for a mobile warehouse site.
     */
    public function assignmentHistory(Request $request, string $id): AnonymousResourceCollection
    {
        $site = WarehouseSite::findOrFail($id);
        $this->authorize('view', $site);

        if ($site->site_type !== 'mobile') {
            return MobileWarehouseAssignmentResource::collection(collect());
        }

        $assignments = MobileWarehouseAssignment::where('warehouse_site_id', $site->id)
            ->with('vehicle')
            ->orderByDesc('assigned_from')
            ->paginate($this->queryService->cappedPerPage($request));

        return MobileWarehouseAssignmentResource::collection($assignments);
    }
}
