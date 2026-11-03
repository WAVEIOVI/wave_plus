<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Controllers\Concerns\AuthorizesInventoryRequests;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Requests\StoreAdjustmentRequest;
use App\Modules\Inventory\Requests\StoreInitialLoadRequest;
use App\Modules\Inventory\Requests\StoreTransferRequest;
use App\Modules\Inventory\Resources\StockMovementResource;
use App\Modules\Inventory\Services\InventoryQueryService;
use App\Modules\Inventory\Services\StockMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class StockMovementController extends Controller
{
    use AuthorizesInventoryRequests;

    public function __construct(
        private readonly StockMovementService $movementService,
        private readonly InventoryQueryService $queryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockMovement::class);

        $query = StockMovement::with([
            'catalogItem',
            'catalogVariant',
            'stockLot',
            'fromLocation',
            'toLocation',
            'performer',
        ]);

        $type = $request->input('filter.movement_type') ?? $request->input('filter.type');
        if ($type) {
            $types = is_array($type) ? $type : explode(',', $type);
            $query->whereIn('movement_type', $types);
        }

        if ($catalogItemId = $request->input('filter.catalog_item_id')) {
            $query->where('catalog_item_id', $catalogItemId);
        }

        if ($from = $request->input('filter.date_from') ?? $request->input('filter.date')) {
            $query->where('performed_at', '>=', $from);
        }
        if ($to = $request->input('filter.date_to')) {
            $query->where('performed_at', '<=', $to);
        }

        if ($search = $request->input('q')) {
            $query->where('reference', 'like', "%{$search}%");
        }

        return StockMovementResource::collection(
            $query->latest('performed_at')->paginate($this->queryService->cappedPerPage($request))
        );
    }

    public function show(string $id): StockMovementResource
    {
        $movement = StockMovement::with([
            'catalogItem',
            'catalogVariant',
            'stockLot',
            'fromLocation.zone.site',
            'toLocation.zone.site',
            'performer',
        ])->findOrFail($id);

        $this->authorize('view', $movement);

        return new StockMovementResource($movement);
    }

    public function storeAdjustment(StoreAdjustmentRequest $request): JsonResponse
    {
        $this->authorize('create', StockMovement::class);
        $validated = $request->validated();

        $movement = $this->movementService->adjustStock(
            catalogItemId: $validated['catalog_item_id'],
            catalogVariantId: $validated['catalog_variant_id'],
            locationId: $validated['location_id'],
            quantity: (string) $validated['quantity'],
            isPlus: $validated['adjustment_type'] === 'plus',
            reason: $validated['reason'],
            performedBy: $this->actorId(),
            stockLotId: $validated['stock_lot_id'] ?? null,
            unitCost: isset($validated['unit_cost']) ? (string) $validated['unit_cost'] : null,
            condition: $validated['condition'] ?? 'new',
        );

        $movement->load(['catalogItem', 'catalogVariant', 'fromLocation', 'toLocation', 'performer']);

        return response()->json([
            'message' => 'Stock adjustment recorded successfully.',
            'data' => new StockMovementResource($movement),
        ], 201);
    }

    public function storeTransfer(StoreTransferRequest $request): JsonResponse
    {
        $this->authorize('create', StockMovement::class);
        $validated = $request->validated();

        $movements = $this->movementService->transferStock(
            catalogItemId: $validated['catalog_item_id'],
            catalogVariantId: $validated['catalog_variant_id'],
            fromLocationId: $validated['from_location_id'],
            toLocationId: $validated['to_location_id'],
            quantity: (string) $validated['quantity'],
            performedBy: $this->actorId(),
            stockLotId: $validated['stock_lot_id'] ?? null,
            reason: $validated['reason'] ?? null,
            condition: $validated['condition'] ?? null,
        );

        return response()->json([
            'message' => 'Stock transfer completed successfully.',
            'data' => StockMovementResource::collection($movements),
        ], 201);
    }

    public function storeInitialLoad(StoreInitialLoadRequest $request): JsonResponse
    {
        $this->authorize('create', StockMovement::class);
        $validated = $request->validated();

        $movements = $this->movementService->initialLoadBatch($validated['items'], $this->actorId());

        collect($movements)->each->load(['catalogItem', 'catalogVariant', 'toLocation', 'performer']);

        return response()->json([
            'message' => 'Initial stock loaded successfully.',
            'data' => StockMovementResource::collection($movements),
        ], 201);
    }

    private function actorId(): string
    {
        $id = Auth::id();
        if (! $id) {
            abort(401);
        }

        return $id;
    }
}
