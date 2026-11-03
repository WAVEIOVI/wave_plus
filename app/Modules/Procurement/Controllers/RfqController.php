<?php

namespace App\Modules\Procurement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Controllers\Concerns\AuthorizesProcurementRequests;
use App\Modules\Procurement\Models\RfqHeader;
use App\Modules\Procurement\Requests\StoreRfqRequest;
use App\Modules\Procurement\Requests\UpdateRfqRequest;
use App\Modules\Procurement\Resources\RfqResource;
use App\Modules\Procurement\Services\ProcurementQueryService;
use App\Modules\Procurement\Services\RfqWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RfqController extends Controller
{
    use AuthorizesProcurementRequests;

    public function __construct(
        private readonly RfqWriteService $writeService,
        private readonly ProcurementQueryService $queryService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RfqHeader::class);

        $query = RfqHeader::with(['lines', 'invitations.supplier', 'awardedToSupplier', 'responses']);

        if ($q = request('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('reference', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%");
            });
        }

        if ($status = request('filter.status')) {
            $query->whereIn('status', explode(',', $status));
        }

        $perPage = $this->queryService->cappedPerPage(request());

        return RfqResource::collection($query->latest()->paginate($perPage));
    }

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', RfqHeader::class);

        $base = RfqHeader::query();

        return response()->json([
            'data' => [
                'total_rfqs' => (clone $base)->count(),
                'draft' => (clone $base)->where('status', 'draft')->count(),
                'awaiting_responses' => (clone $base)->where('status', 'awaiting_responses')->count(),
                'under_evaluation' => (clone $base)->where('status', 'under_evaluation')->count(),
                'awarded' => (clone $base)->where('status', 'awarded')->count(),
            ],
        ]);
    }

    public function store(StoreRfqRequest $request): JsonResponse
    {
        $this->authorize('create', RfqHeader::class);

        $rfq = $this->writeService->create($request->validated());
        $rfq->load($this->detailLoads());

        return response()->json([
            'message' => 'Request for Quotation created successfully.',
            'data' => new RfqResource($rfq),
        ], 201);
    }

    public function show(RfqHeader $rfq): RfqResource
    {
        $this->authorize('view', $rfq);
        $rfq->load(array_merge($this->detailLoads(), [
            'responses.supplier',
            'responses.responseLines.rfqLine.variant',
            'responses.responseLines.rfqLine.catalogItem',
        ]));

        return new RfqResource($rfq);
    }

    public function update(UpdateRfqRequest $request, RfqHeader $rfq): JsonResponse
    {
        $this->authorize('update', $rfq);

        $rfq = $this->writeService->update($rfq, $request->validated());
        $rfq->load(array_merge($this->detailLoads(), ['responses.supplier']));

        return response()->json([
            'message' => 'Request for Quotation updated successfully.',
            'data' => new RfqResource($rfq),
        ]);
    }

    public function destroy(RfqHeader $rfq): JsonResponse
    {
        $this->authorize('delete', $rfq);
        $this->writeService->delete($rfq);

        return response()->json([
            'message' => 'Request for Quotation deleted successfully.',
        ]);
    }

    public function updateStatus(RfqHeader $rfq): JsonResponse
    {
        $status = request('status');
        if (! $status) {
            return response()->json(['message' => 'Status is required.'], 422);
        }

        if ($status === 'awarded') {
            $this->authorize('award', $rfq);
        } else {
            $this->authorize('update', $rfq);
        }

        $rfq = $this->writeService->transition(
            $rfq,
            (string) $status,
            request()->user(),
            request('awarded_to_supplier_id') ? request('awarded_to_supplier_id') : null
        );

        return response()->json([
            'message' => "RFQ status updated to {$status}.",
            'data' => new RfqResource($rfq->load(['lines', 'invitations.supplier'])),
        ]);
    }

    private function detailLoads(): array
    {
        return [
            'lines.catalogItem.product',
            'lines.catalogItem.consumable',
            'lines.catalogItem.eqServicing',
            'lines.variant',
            'lines.unit',
            'invitations.supplier',
            'awardedToSupplier',
            'awardedByUser',
        ];
    }
}
