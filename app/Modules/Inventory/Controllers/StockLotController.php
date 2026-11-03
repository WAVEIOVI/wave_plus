<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Controllers\Concerns\AuthorizesInventoryRequests;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Requests\StoreStockLotRequest;
use App\Modules\Inventory\Resources\StockLotResource;
use App\Modules\Inventory\Services\InventoryQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class StockLotController extends Controller
{
    use AuthorizesInventoryRequests;

    public function __construct(
        private readonly InventoryQueryService $queryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockLot::class);

        $query = StockLot::with(['catalogItem', 'supplier'])
            ->withCount(['serialNumbers', 'stockItems']);

        // Filter by catalog item
        if ($catalogItemId = $request->input('filter.catalog_item_id')) {
            $query->where('catalog_item_id', $catalogItemId);
        }

        // Filter by status
        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        // Search by lot number
        if ($search = $request->input('q')) {
            $query->where('lot_number', 'like', "%{$search}%");
        }

        // Expiry filter: lots expiring within N days
        if ($expiryDays = $request->input('filter.expiry_within_days')) {
            $query->whereNotNull('expiry_date')
                ->where('expiry_date', '<=', now()->addDays((int) $expiryDays))
                ->where('expiry_date', '>=', now());
        }

        return StockLotResource::collection(
            $query->latest()->paginate($this->queryService->cappedPerPage($request))
        );
    }

    /**
     * Show a single stock lot with relations.
     */
    public function show(string $id): StockLotResource
    {
        $lot = StockLot::with(['catalogItem', 'supplier', 'serialNumbers', 'stockItems.location.zone.site'])
            ->withCount(['serialNumbers', 'stockItems'])
            ->findOrFail($id);

        $this->authorize('view', $lot);

        return new StockLotResource($lot);
    }

    /**
     * Create a stock lot manually.
     */
    public function store(StoreStockLotRequest $request): JsonResponse
    {
        $this->authorize('create', StockLot::class);
        $validated = $request->validated();
        $validated['created_by'] = Auth::id();
        $validated['status'] = $validated['status'] ?? 'active';

        $lot = StockLot::create($validated);
        $lot->load(['catalogItem', 'supplier']);
        $lot->loadCount(['serialNumbers', 'stockItems']);

        return response()->json([
            'message' => 'Stock lot created successfully.',
            'data' => new StockLotResource($lot),
        ], 201);
    }

    /**
     * Update a stock lot (status, notes, expiry_date only).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $lot = StockLot::findOrFail($id);
        $this->authorize('update', $lot);

        $validated = $request->validate([
            'status' => ['sometimes', 'in:active,expired,quarantined,recalled'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $lot->update($validated);
        $lot->load(['catalogItem', 'supplier']);
        $lot->loadCount(['serialNumbers', 'stockItems']);

        return response()->json([
            'message' => 'Stock lot updated successfully.',
            'data' => new StockLotResource($lot),
        ]);
    }

    /**
     * Soft delete a stock lot only if no active stock items reference it.
     */
    public function destroy(string $id): JsonResponse
    {
        $lot = StockLot::findOrFail($id);
        $this->authorize('delete', $lot);

        $hasActiveStock = $lot->stockItems()
            ->where('qty_on_hand', '>', 0)
            ->exists();

        if ($hasActiveStock) {
            return response()->json([
                'message' => 'Cannot delete lot: it has active stock items.',
            ], 422);
        }

        $lot->delete();

        return response()->json([
            'message' => 'Stock lot deleted successfully.',
        ]);
    }
}
