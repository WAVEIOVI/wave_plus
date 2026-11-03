<?php

declare(strict_types=1);

namespace App\Modules\Crm\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Controllers\Concerns\AuthorizesCrmRequests;
use App\Modules\Crm\Enums\RfqStatus;
use App\Modules\Crm\Models\PriceRequest;
use App\Modules\Crm\Requests\StorePriceRequestRequest;
use App\Modules\Crm\Requests\UpdatePriceRequestRequest;
use App\Modules\Crm\Resources\PriceRequestResource;
use App\Modules\Crm\Services\CrmQueryService;
use App\Modules\Crm\Services\PriceRequestWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PriceRequestController extends Controller
{
    use AuthorizesCrmRequests;

    public function __construct(
        private readonly PriceRequestWriteService $writeService,
        private readonly CrmQueryService $queryService,
    ) {}

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', PriceRequest::class);
        $base = PriceRequest::query();

        return response()->json([
            'data' => [
                'total_requests' => (clone $base)->count(),
                'pending_internal' => (clone $base)->where('status', 'pending_internal')->count(),
                'costing' => (clone $base)->where('status', 'costing')->count(),
                'responded' => (clone $base)->where('status', 'responded')->count(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PriceRequest::class);
        $query = PriceRequest::with(['client', 'assignedToUser']);

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($query) use ($search) {
                $query->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('legal_name', 'like', "%{$search}%")
                            ->orWhere('commercial_name', 'like', "%{$search}%");
                    });
            });
        }

        $status = $request->input('filter.status', $request->input('status'));
        if ($status) {
            $query->where('status', $status);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        return PriceRequestResource::collection(
            $query->latest()->paginate($this->queryService->cappedPerPage($request))
        );
    }

    public function show(PriceRequest $priceRequest): PriceRequestResource
    {
        $this->authorize('view', $priceRequest);
        $priceRequest->load([
            'client',
            'assignedToUser',
            'lines.catalogItem.product',
            'lines.catalogItem.consumable',
            'lines.catalogItem.eqServicing',
            'lines.variant',
            'convertedToQuotation',
        ]);

        return new PriceRequestResource($priceRequest);
    }

    public function store(StorePriceRequestRequest $request): JsonResponse
    {
        $this->authorize('create', PriceRequest::class);
        $pr = $this->writeService->create($request->validated());

        return (new PriceRequestResource($pr->load(['client', 'lines.catalogItem.product', 'lines.catalogItem.consumable', 'lines.catalogItem.eqServicing', 'lines.variant'])))->response()->setStatusCode(201);
    }

    public function update(UpdatePriceRequestRequest $request, PriceRequest $priceRequest): PriceRequestResource
    {
        $this->authorize('update', $priceRequest);
        $priceRequest = $this->writeService->update($priceRequest, $request->validated());

        return new PriceRequestResource($priceRequest->load(['client', 'lines.catalogItem', 'lines.variant']));
    }

    public function updateStatus(Request $request, PriceRequest $priceRequest): PriceRequestResource
    {
        $this->authorize('update', $priceRequest);
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::enum(RfqStatus::class)],
        ]);
        $priceRequest = $this->writeService->transition($priceRequest, $validated['status']);

        return new PriceRequestResource($priceRequest->load(['client', 'lines']));
    }

    public function destroy(PriceRequest $priceRequest): JsonResponse
    {
        $this->authorize('delete', $priceRequest);
        $this->writeService->delete($priceRequest);

        return response()->noContent();
    }

    public function addLinesFromBlueprint(Request $request, PriceRequest $priceRequest): PriceRequestResource
    {
        $this->authorize('update', $priceRequest);
        $request->validate([
            'blueprint_id' => ['required', 'ulid', 'exists:equipment_blueprints,id'],
            'selected_option_values' => ['nullable', 'array'],
            'selected_servicings' => ['nullable', 'array'],
            'variant_selections' => ['nullable', 'array'],
            'equipment_qty' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $updated = $this->writeService->addLinesFromBlueprint($priceRequest, $request->all());

        return new PriceRequestResource($updated->load([
            'client',
            'lines.catalogItem',
            'lines.variant',
        ]));
    }
}
