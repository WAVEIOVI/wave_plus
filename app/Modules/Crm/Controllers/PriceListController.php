<?php

declare(strict_types=1);

namespace App\Modules\Crm\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Controllers\Concerns\AuthorizesCrmRequests;
use App\Modules\Crm\Models\PriceList;
use App\Modules\Crm\Requests\StorePriceListRequest;
use App\Modules\Crm\Requests\UpdatePriceListRequest;
use App\Modules\Crm\Resources\PriceListResource;
use App\Modules\Crm\Services\CrmQueryService;
use App\Modules\Crm\Services\PriceListWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PriceListController extends Controller
{
    use AuthorizesCrmRequests;

    public function __construct(
        private readonly PriceListWriteService $writeService,
        private readonly CrmQueryService $queryService,
    ) {}

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', PriceList::class);
        $base = PriceList::query();
        $today = now()->toDateString();

        return response()->json([
            'data' => [
                'total_price_lists' => (clone $base)->count(),
                'draft' => (clone $base)->where('is_active', false)->count(),
                'active' => (clone $base)->where('is_active', true)->where(function ($query) use ($today) {
                    $query->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
                })->count(),
                'expired' => (clone $base)->where('is_active', true)->where('valid_to', '<', $today)->count(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PriceList::class);
        $query = PriceList::with(['client']);

        if ($request->filled('q')) {
            $query->where(function ($query) use ($request) {
                $query->where('code', 'like', '%'.$request->q.'%')
                    ->orWhere('name', 'like', '%'.$request->q.'%');
            });
        }

        $status = data_get($request->input('filter'), 'status');
        if ($status === 'draft') {
            $query->where('is_active', false);
        } elseif ($status === 'active') {
            $query->where('is_active', true)->where(function ($query) {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', today());
            });
        } elseif ($status === 'expired') {
            $query->where('is_active', true)->whereDate('valid_to', '<', today());
        }

        return PriceListResource::collection(
            $query->latest()->paginate($this->queryService->cappedPerPage($request))
        );
    }

    public function show(PriceList $priceList): PriceListResource
    {
        $this->authorize('view', $priceList);

        return new PriceListResource($priceList->load(['client', 'lines.catalogItem', 'lines.variant']));
    }

    public function store(StorePriceListRequest $request): JsonResponse
    {
        $this->authorize('create', PriceList::class);
        $priceList = $this->writeService->create($request->validated());

        return (new PriceListResource($priceList->load(['client', 'lines'])))->response()->setStatusCode(201);
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList): PriceListResource
    {
        $this->authorize('update', $priceList);
        $priceList = $this->writeService->update($priceList, $request->validated());

        return new PriceListResource($priceList->load(['client', 'lines']));
    }

    public function updateStatus(Request $request, PriceList $priceList): PriceListResource
    {
        $this->authorize('update', $priceList);
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['draft', 'active', 'expired'])],
        ]);
        $priceList = $this->writeService->transition($priceList, $validated['status']);

        return new PriceListResource($priceList->load(['client', 'lines.catalogItem', 'lines.variant']));
    }

    public function destroy(PriceList $priceList): JsonResponse
    {
        $this->authorize('delete', $priceList);
        $this->writeService->delete($priceList);

        return response()->noContent();
    }

    public function addLinesFromBlueprint(Request $request, PriceList $priceList): PriceListResource
    {
        $this->authorize('update', $priceList);
        $request->validate([
            'blueprint_id' => ['required', 'ulid', 'exists:equipment_blueprints,id'],
            'selected_option_values' => ['nullable', 'array'],
            'selected_servicings' => ['nullable', 'array'],
            'variant_selections' => ['nullable', 'array'],
            'equipment_qty' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $updated = $this->writeService->addLinesFromBlueprint($priceList, $request->all());

        return new PriceListResource($updated->load([
            'client',
            'lines.catalogItem',
            'lines.variant',
        ]));
    }
}
