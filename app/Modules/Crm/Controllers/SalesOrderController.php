<?php

declare(strict_types=1);

namespace App\Modules\Crm\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Controllers\Concerns\AuthorizesCrmRequests;
use App\Modules\Crm\Models\Contract;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Requests\SalesOrderFeasibilityRequest;
use App\Modules\Crm\Requests\StoreSalesOrderRequest;
use App\Modules\Crm\Requests\UpdateSalesOrderRequest;
use App\Modules\Crm\Resources\SalesOrderResource;
use App\Modules\Crm\Services\CrmQueryService;
use App\Modules\Crm\Services\FeasibilityService;
use App\Modules\Crm\Services\SalesOrderConversionService;
use App\Modules\Crm\Services\SalesOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class SalesOrderController extends Controller
{
    use AuthorizesCrmRequests;

    public function __construct(
        private SalesOrderService $salesOrderService,
        private FeasibilityService $feasibilityService,
        private SalesOrderConversionService $conversionService,
        private CrmQueryService $queryService,
        private \App\Modules\Crm\Services\SalesOrderWizardService $wizardService,
    ) {}

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', SalesOrder::class);
        $base = SalesOrder::query();

        return response()->json([
            'data' => [
                'total_sales_orders' => (clone $base)->count(),
                'sales_count' => (clone $base)->where('so_type', 'sales')->count(),
                'service_count' => (clone $base)->where('so_type', 'service')->count(),
                'draft' => (clone $base)->where('status', 'draft')->count(),
                'submitted' => (clone $base)->where('status', 'submitted')->count(),
                'under_review' => (clone $base)->where('status', 'under_review')->count(),
                'accepted' => (clone $base)->where('status', 'accepted')->count(),
                'completed' => (clone $base)->where('status', 'completed')->count(),
                'closed' => (clone $base)->where('status', 'closed')->count(),
                'rejected' => (clone $base)->where('status', 'rejected')->count(),
                'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesOrder::class);

        $query = SalesOrder::with(['client', 'contract', 'quotation', 'lines.catalogItem', 'deliveryWorkOrders', 'serviceWorkOrders']);

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('client_po_reference', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('legal_name', 'like', "%{$search}%")
                            ->orWhere('commercial_name', 'like', "%{$search}%");
                    });
            });
        }

        $soTypeFilter = $request->input('so_type') ?? $request->input('filter.so_type');
        if ($soTypeFilter) {
            $query->where('so_type', $soTypeFilter === 'goods' ? 'sales' : $soTypeFilter);
        }
        if ($request->filled('creation_path')) {
            $query->where('creation_path', $request->creation_path);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('contract_id')) {
            $query->where('contract_id', $request->contract_id);
        }
        if ($request->filled('quotation_id')) {
            $query->where('quotation_id', $request->quotation_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return SalesOrderResource::collection(
            $query->latest()->paginate($this->queryService->cappedPerPage($request))
        );
    }

    public function show(SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('view', $salesOrder);
        $salesOrder->load([
            'client',
            'quotation',
            'contract',
            'priceList',
            'confirmedByUser',
            'reviewedByUser',
            'lsaDocument',
            'gcsDocument',
            'lines.catalogItem.variants',
            'lines.variant',
            'lines.unit',
            'locations.bpAddress',
            'locations.clientSite',
            'deliveryWorkOrders.lines',
            'serviceWorkOrders',
        ]);

        return new SalesOrderResource($salesOrder);
    }

    public function wizard(Request $request): JsonResponse
    {
        $this->authorize('create', SalesOrder::class);
        $salesOrder = $this->wizardService->initFromWizard($request->all());

        return (new SalesOrderResource($salesOrder))->response()->setStatusCode(201);
    }

    public function commitmentDocument(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('view', $salesOrder);

        $isService = $salesOrder->so_type === \App\Modules\Crm\Enums\SalesOrderType::Service
            || (is_string($salesOrder->so_type) && $salesOrder->so_type === 'service')
            || ($salesOrder->so_type instanceof \BackedEnum && $salesOrder->so_type->value === 'service');

        if ($isService) {
            $doc = $salesOrder->lsaDocument;
            $type = 'lsa';
        } else {
            $doc = $salesOrder->gcsDocument;
            $type = 'gcs';
        }

        if (! $doc) {
            return response()->json([
                'data' => null,
                'message' => 'No commitment document generated yet for this Sales Order.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'type' => $type,
                'document' => $doc,
                'sales_order' => [
                    'id' => $salesOrder->id,
                    'reference' => $salesOrder->reference,
                    'client' => $salesOrder->client ? [
                        'id' => $salesOrder->client->id,
                        'name' => $salesOrder->client->commercial_name ?? $salesOrder->client->legal_name,
                    ] : null,
                ],
            ],
        ]);
    }

    public function store(StoreSalesOrderRequest $request): JsonResponse
    {
        $this->authorize('create', SalesOrder::class);
        $salesOrder = $this->salesOrderService->createSalesOrder($request->validated());

        return (new SalesOrderResource($salesOrder))->response()->setStatusCode(201);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('update', $salesOrder);

        return new SalesOrderResource($this->salesOrderService->updateSalesOrder($salesOrder, $request->validated()));
    }

    public function submit(SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('update', $salesOrder);

        return new SalesOrderResource($this->salesOrderService->submitForReview($salesOrder));
    }

    public function startFeasibilityReview(SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('approve', $salesOrder);

        return new SalesOrderResource($this->salesOrderService->startFeasibilityReview($salesOrder));
    }

    public function stockCheck(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('view', $salesOrder);

        return response()->json(['data' => $this->feasibilityService->checkStock($salesOrder)]);
    }

    public function creditCheck(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('view', $salesOrder);

        return response()->json(['data' => $this->feasibilityService->checkCredit($salesOrder)]);
    }

    public function feasibilityReview(SalesOrderFeasibilityRequest $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('approve', $salesOrder);
        $reviewerId = Auth::id();
        if (! $reviewerId) {
            abort(401);
        }

        return new SalesOrderResource($this->salesOrderService->reviewFeasibility($salesOrder, $request->validated(), $reviewerId));
    }

    public function accept(SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('approve', $salesOrder);
        $actorId = Auth::id();
        if (! $actorId) {
            abort(401);
        }

        return new SalesOrderResource($this->salesOrderService->acceptSalesOrder($salesOrder, $actorId));
    }

    public function reject(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('approve', $salesOrder);
        $request->validate(['rejection_reason' => 'required|string']);
        $actorId = Auth::id();
        if (! $actorId) {
            abort(401);
        }

        return new SalesOrderResource($this->salesOrderService->rejectSalesOrder($salesOrder, $request->rejection_reason, $actorId));
    }

    public function cancel(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('update', $salesOrder);
        $actorId = Auth::id();
        if (! $actorId) {
            abort(401);
        }

        return new SalesOrderResource($this->salesOrderService->cancelSalesOrder($salesOrder, $request->input('reason'), $actorId));
    }

    public function createRevision(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('update', $salesOrder);
        $actorId = Auth::id();
        if (! $actorId) {
            abort(401);
        }

        return new SalesOrderResource($this->salesOrderService->createAmendment($salesOrder, $request->all(), $actorId));
    }

    public function convertFromQuotation(Quotation $quotation): JsonResponse
    {
        $this->authorize('create', SalesOrder::class);
        $salesOrders = $this->conversionService->createFromQuotation($quotation);

        if ($salesOrders->count() === 1) {
            return (new SalesOrderResource($salesOrders->first()))
                ->additional([
                    'meta' => [
                        'count' => 1,
                        'split' => false,
                        'primary_so_id' => $salesOrders->first()->id,
                    ],
                ])
                ->response()
                ->setStatusCode(201);
        }

        return response()->json([
            'data' => SalesOrderResource::collection($salesOrders),
            'meta' => [
                'count' => $salesOrders->count(),
                'split' => true,
                'primary_so_id' => $salesOrders->first()?->id,
            ],
        ], 201);
    }

    public function convertFromContract(Contract $contract): JsonResponse
    {
        $this->authorize('create', SalesOrder::class);
        $salesOrder = $this->conversionService->createFromContract($contract);

        return (new SalesOrderResource($salesOrder))->response()->setStatusCode(201);
    }

    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('delete', $salesOrder);
        $this->salesOrderService->delete($salesOrder);

        return response()->json(null, 204);
    }

    public function addLinesFromBlueprint(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        $this->authorize('update', $salesOrder);
        $request->validate([
            'blueprint_id' => ['required', 'ulid', 'exists:equipment_blueprints,id'],
            'selected_option_values' => ['nullable', 'array'],
            'selected_servicings' => ['nullable', 'array'],
            'variant_selections' => ['nullable', 'array'],
            'equipment_qty' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $updated = $this->salesOrderService->addLinesFromBlueprint($salesOrder, $request->all());

        return new SalesOrderResource($updated);
    }

    public function locations(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('view', $salesOrder);

        return response()->json([
            'data' => $salesOrder->locations()->with(['bpAddress', 'clientSite'])->get(),
        ]);
    }

    public function addLocation(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('update', $salesOrder);
        $data = $request->validate([
            'bp_address_id' => ['required', 'ulid', 'exists:bp_addresses,id'],
            'client_site_id' => ['nullable', 'ulid', 'exists:client_sites,id'],
            'purpose' => ['nullable', 'string', 'in:delivery,service,billing'],
            'is_primary' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $location = $this->salesOrderService->addLocation($salesOrder, $data);

        return response()->json([
            'data' => $location->load(['bpAddress', 'clientSite']),
        ], 201);
    }

    public function removeLocation(SalesOrder $salesOrder, \App\Modules\Crm\Models\SalesOrderLocation $location): JsonResponse
    {
        $this->authorize('update', $salesOrder);
        $this->salesOrderService->removeLocation($salesOrder, $location);

        return response()->json(null, 204);
    }
}
