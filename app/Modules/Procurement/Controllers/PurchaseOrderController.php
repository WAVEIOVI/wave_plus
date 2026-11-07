<?php

namespace App\Modules\Procurement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Controllers\Concerns\AuthorizesProcurementRequests;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Requests\StorePurchaseOrderRequest;
use App\Modules\Procurement\Requests\UpdatePurchaseOrderRequest;
use App\Modules\Procurement\Resources\PurchaseOrderResource;
use App\Modules\Procurement\Services\ProcurementQueryService;
use App\Modules\Procurement\Services\PurchaseOrderWriteService;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    use AuthorizesProcurementRequests;

    public function __construct(
        private readonly PurchaseOrderWriteService $writeService,
        private readonly ProcurementQueryService $queryService,
    ) {}

    public function meta()
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        return response()->json([
            'data' => [
                'total_pos' => PurchaseOrder::count(),
                'pending_approval' => PurchaseOrder::where('status', 'pending_approval')->count(),
                'in_delivery' => PurchaseOrder::where('status', 'in_delivery')->count(),
                'fully_received' => PurchaseOrder::where('status', 'fully_received')->count(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $query = PurchaseOrder::with(['supplier']);

        if ($q = $request->input('q')) {
            $query->where('reference', 'like', "%{$q}%");
        }

        if ($status = $request->input('filter.status')) {
            $query->whereIn('status', explode(',', $status));
        }

        if ($supplierId = $request->input('filter.supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($siteId = $request->input('filter.site_id')) {
            $query->where('site_id', $siteId);
        }

        $perPage = $this->queryService->cappedPerPage($request);

        return PurchaseOrderResource::collection($query->latest()->paginate($perPage));
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('view', $purchaseOrder);
        $purchaseOrder->load(['supplier', 'site', 'goodsReceiptNotes.lines', 'lines.catalogItem.product', 'lines.catalogItem.consumable', 'lines.catalogItem.eqServicing', 'lines.variant']);

        return new PurchaseOrderResource($purchaseOrder);
    }

    public function store(StorePurchaseOrderRequest $request)
    {
        $this->authorize('create', PurchaseOrder::class);

        $purchaseOrder = $this->writeService->create($request->validated());

        return (new PurchaseOrderResource($purchaseOrder->load(['supplier', 'lines.catalogItem.product', 'lines.catalogItem.consumable', 'lines.catalogItem.eqServicing', 'lines.variant'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('update', $purchaseOrder);

        $purchaseOrder = $this->writeService->update($purchaseOrder, $request->validated());

        return new PurchaseOrderResource($purchaseOrder->load(['supplier', 'lines.catalogItem.product', 'lines.catalogItem.consumable', 'lines.catalogItem.eqServicing', 'lines.variant']));
    }

    public function updateStatus(PurchaseOrder $purchaseOrder)
    {
        $validated = request()->validate([
            'status' => 'required|string',
            'approval_note' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
        ]);

        if ($validated['status'] === 'approved') {
            $this->authorize('approve', $purchaseOrder);
        } else {
            $this->authorize('update', $purchaseOrder);
        }

        $purchaseOrder = $this->writeService->transition($purchaseOrder, $validated, request()->user());

        return response()->json([
            'message' => 'PO status updated successfully.',
            'data' => new PurchaseOrderResource($purchaseOrder->load(['supplier', 'lines.catalogItem', 'lines.variant'])),
        ]);
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('delete', $purchaseOrder);
        $this->writeService->delete($purchaseOrder);

        return response()->noContent();
    }
}
