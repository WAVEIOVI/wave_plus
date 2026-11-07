<?php

namespace App\Modules\Procurement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Controllers\Concerns\AuthorizesProcurementRequests;
use App\Modules\Procurement\Models\InternalPurchaseRequest;
use App\Modules\Procurement\Requests\StoreIprRequest;
use App\Modules\Procurement\Requests\UpdateIprRequest;
use App\Modules\Procurement\Resources\IprResource;
use App\Modules\Procurement\Services\IprWriteService;
use App\Modules\Procurement\Services\ProcurementQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IprController extends Controller
{
    use AuthorizesProcurementRequests;

    public function __construct(
        private readonly IprWriteService $writeService,
        private readonly ProcurementQueryService $queryService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InternalPurchaseRequest::class);

        $query = InternalPurchaseRequest::with(['requestedBy', 'assignedTo', 'lines']);

        if ($q = request('q')) {
            $query->where('reference', 'like', "%{$q}%");
        }

        if ($status = request('filter.status')) {
            $statuses = explode(',', $status);
            $query->whereIn('status', $statuses);
        }

        if ($urgency = request('filter.urgency')) {
            $query->where('urgency', $urgency);
        }

        if ($triggerSource = request('filter.trigger_source')) {
            $query->where('trigger_source', $triggerSource);
        }

        $perPage = $this->queryService->cappedPerPage(request());

        return IprResource::collection($query->latest()->paginate($perPage));
    }

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', InternalPurchaseRequest::class);

        $base = InternalPurchaseRequest::query();

        return response()->json([
            'data' => [
                'total_iprs' => (clone $base)->count(),
                'pending_approval' => (clone $base)->where('status', 'pending_approval')->count(),
                'approved' => (clone $base)->where('status', 'approved')->count(),
                'urgent_requests' => (clone $base)->whereIn('urgency', ['urgent', 'critical'])->count(),
                'converted_to_po' => (clone $base)->where('status', 'converted_to_po')->count(),
            ],
        ]);
    }

    public function store(StoreIprRequest $request): JsonResponse
    {
        $this->authorize('create', InternalPurchaseRequest::class);

        $ipr = $this->writeService->create($request->validated());
        $ipr->load(['requestedBy', 'assignedTo', 'lines.catalogItem.product', 'lines.catalogItem.consumable', 'lines.catalogItem.eqServicing', 'lines.variant', 'lines.suggestedSupplier', 'po']);

        return response()->json([
            'message' => 'Internal Purchase Request created successfully.',
            'data' => new IprResource($ipr),
        ], 201);
    }

    public function show(InternalPurchaseRequest $ipr): IprResource
    {
        $this->authorize('view', $ipr);
        $ipr->load(['requestedBy', 'assignedTo', 'mergedInto', 'lines.catalogItem.uom', 'lines.catalogItem.product', 'lines.catalogItem.consumable', 'lines.catalogItem.eqServicing', 'lines.variant', 'lines.suggestedSupplier', 'po']);

        return new IprResource($ipr);
    }

    public function update(UpdateIprRequest $request, InternalPurchaseRequest $ipr): JsonResponse
    {
        $this->authorize('update', $ipr);

        $ipr = $this->writeService->update($ipr, $request->validated());
        $ipr->load(['requestedBy', 'assignedTo', 'lines.catalogItem', 'lines.variant', 'lines.suggestedSupplier', 'po']);

        return response()->json([
            'message' => 'Internal Purchase Request updated successfully.',
            'data' => new IprResource($ipr),
        ]);
    }

    public function updateStatus(InternalPurchaseRequest $ipr): JsonResponse
    {
        $validated = request()->validate([
            'status' => 'required|string',
            'approval_note' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
        ]);

        if (in_array($validated['status'], ['approved', 'rejected'], true)) {
            $this->authorize('approve', $ipr);
        } else {
            $this->authorize('update', $ipr);
        }

        $ipr = $this->writeService->transition($ipr, $validated);

        return response()->json([
            'message' => 'IPR status updated successfully.',
            'data' => new IprResource($ipr->load(['requestedBy', 'assignedTo', 'po'])),
        ]);
    }

    public function destroy(InternalPurchaseRequest $ipr): JsonResponse
    {
        $this->authorize('delete', $ipr);
        $this->writeService->delete($ipr);

        return response()->json([
            'message' => 'Internal Purchase Request deleted successfully.',
        ]);
    }
}
