<?php

declare(strict_types=1);

namespace App\Modules\Crm\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Controllers\Concerns\AuthorizesCrmRequests;
use App\Modules\Crm\Enums\QuotationStatus;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Requests\StoreQuotationRequest;
use App\Modules\Crm\Requests\UpdateQuotationRequest;
use App\Modules\Crm\Resources\QuotationResource;
use App\Modules\Crm\Services\CrmQueryService;
use App\Modules\Crm\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    use AuthorizesCrmRequests;

    public function __construct(
        private QuotationService $quotationService,
        private CrmQueryService $queryService,
    ) {}

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', Quotation::class);
        $base = Quotation::query();

        return response()->json([
            'data' => [
                'total_quotes' => (clone $base)->count(),
                'draft' => (clone $base)->where('status', 'draft')->count(),
                'sent' => (clone $base)->where('status', 'sent')->count(),
                'accepted' => (clone $base)->where('status', 'accepted')->count(),
                'rejected' => (clone $base)->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Quotation::class);

        $query = Quotation::with(['client']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        return QuotationResource::collection(
            $query->latest()->paginate($this->queryService->cappedPerPage($request))
        );
    }

    public function show(Quotation $quotation): QuotationResource
    {
        $this->authorize('view', $quotation);
        $quotation->load([
            'client',
            'parentQuotation',
            'priceRequest',
            'contract',
            'approvedByUser',
            'lines.catalogItem.product',
            'lines.catalogItem.consumable',
            'lines.catalogItem.eqServicing',
            'lines.variant',
        ]);

        return new QuotationResource($quotation);
    }

    public function store(StoreQuotationRequest $request): JsonResponse
    {
        $this->authorize('create', Quotation::class);
        $quotation = $this->quotationService->createQuotation($request->validated());

        return (new QuotationResource($quotation->load([
            'client',
            'parentQuotation',
            'priceRequest',
            'contract',
            'approvedByUser',
            'lines.catalogItem.product',
            'lines.catalogItem.consumable',
            'lines.catalogItem.eqServicing',
            'lines.variant',
        ])))->response()->setStatusCode(201);
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('update', $quotation);
        $quotation = $this->quotationService->updateQuotation($quotation, $request->validated());

        return new QuotationResource($quotation->load([
            'client',
            'parentQuotation',
            'priceRequest',
            'contract',
            'approvedByUser',
            'lines.catalogItem.product',
            'lines.catalogItem.consumable',
            'lines.catalogItem.eqServicing',
            'lines.variant',
        ]));
    }

    public function destroy(Quotation $quotation): JsonResponse
    {
        $this->authorize('delete', $quotation);
        $this->quotationService->delete($quotation);

        return response()->noContent();
    }

    public function updateStatus(Request $request, Quotation $quotation): QuotationResource
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::enum(QuotationStatus::class)],
        ]);

        if ($validated['status'] === QuotationStatus::Accepted->value) {
            $this->authorize('approve', $quotation);
        } else {
            $this->authorize('update', $quotation);
        }

        $quotation = $this->quotationService->transition($quotation, $validated['status'], [
            'approved_by' => Auth::id(),
        ]);

        return new QuotationResource($quotation->load([
            'client',
            'parentQuotation',
            'priceRequest',
            'contract',
            'approvedByUser',
            'lines.catalogItem',
            'lines.variant',
        ]));
    }

    public function createRevision(Quotation $quotation): QuotationResource
    {
        $this->authorize('update', $quotation);
        $revision = $quotation->createRevision();

        return new QuotationResource($revision->load(['client', 'lines', 'parentQuotation', 'priceRequest', 'contract', 'approvedByUser']));
    }

    public function addLinesFromBlueprint(Request $request, Quotation $quotation): QuotationResource
    {
        $this->authorize('update', $quotation);
        $request->validate([
            'blueprint_id' => ['required', 'ulid', 'exists:equipment_blueprints,id'],
            'selected_option_values' => ['nullable', 'array'],
            'selected_servicings' => ['nullable', 'array'],
            'variant_selections' => ['nullable', 'array'],
            'equipment_qty' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $updated = $this->quotationService->addLinesFromBlueprint($quotation, $request->all());

        return new QuotationResource($updated->load([
            'client',
            'lines.catalogItem',
            'lines.variant',
        ]));
    }
}
