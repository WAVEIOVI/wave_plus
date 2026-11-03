<?php

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Controllers\Concerns\AuthorizesCatalogRequests;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Requests\StoreCatalogItemRequest;
use App\Modules\Catalog\Requests\UpdateCatalogItemRequest;
use App\Modules\Catalog\Resources\CatalogItemResource;
use App\Modules\Catalog\Services\CatalogItemWriteService;
use App\Modules\Catalog\Services\CatalogQueryService;
use App\Modules\Catalog\Traits\GeneratesPdf;
use App\Modules\Catalog\Traits\HasPartnerActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class CatalogItemController extends Controller
{
    use AuthorizesCatalogRequests, GeneratesPdf, HasPartnerActions;

    protected string $partnerType = 'catalog_item';

    protected string $modelClass = CatalogItem::class;

    public function __construct(
        private readonly CatalogQueryService $queryService,
        private readonly CatalogItemWriteService $writeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CatalogItem::class);

        $query = QueryBuilder::for(CatalogItem::with([
            'category',
            'brand',
            'uom',
            'media',
            // Selective eager-loads for sub_type — only the FK + discriminator column.
            // This avoids N+1 queries and keeps index payloads small.
            'product:catalog_item_id,product_type',
            'consumable:catalog_item_id,consumable_type',
            'eqServicing:catalog_item_id,eq_servicing_type',
        ]))
            ->allowedFilters([
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('item_type'),
                AllowedFilter::callback('product_type', function ($query, $value) {
                    $query->whereHas('product', fn ($pq) => $pq->where('product_type', $value));
                }),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('brand_id'),
                AllowedFilter::exact('is_stockable'),
                AllowedFilter::exact('is_sellable'),
            ])
            ->allowedSorts([
                AllowedSort::field('id'),
                AllowedSort::field('code'),
                AllowedSort::field('item_type'),
                AllowedSort::field('status'),
                AllowedSort::field('created_at'),
            ])
            ->allowedIncludes([
                'product',
                'consumable',
                'eqServicing',
                'category',
                'brand',
                'suppliers',
                'options.values',
                'variants.optionValues',
                'variants.stockItems',
                'uom',
                AllowedInclude::relationship('preferred_supplier', 'preferredSupplier'),
                'preferredSupplier',
            ])
            ->defaultSort('-created_at');

        $this->queryService->applySearch($query, $request, ['code', 'name', 'short_description']);

        return $this->queryService->paginate($query, $request, CatalogItemResource::class);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CatalogItem::class);

        return $this->queryService->itemOptions(CatalogItem::query(), $request);
    }

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', CatalogItem::class);

        return response()->json([
            'data' => [
                'total_items' => CatalogItem::count(),
                'total_active' => CatalogItem::where('status', 'active')->count(),
                'total_products' => CatalogItem::where('item_type', 'product')->count(),
                'total_consumables' => CatalogItem::where('item_type', 'consumable')->count(),
                'total_servicing' => CatalogItem::where('item_type', 'eq_servicing')->count(),
            ],
        ]);
    }

    public function store(StoreCatalogItemRequest $request): JsonResponse
    {
        $this->authorize('create', CatalogItem::class);

        $item = $this->writeService->create($request->validated(), $request);

        return response()->json([
            'message' => 'Catalog item created successfully.',
            'data' => new CatalogItemResource($item),
        ], 201);
    }

    public function show(string $catalogItem): JsonResponse
    {
        $record = CatalogItem::with([
            'category',
            'brand',
            'uom',
            'product',
            'consumable',
            'eqServicing',
            'suppliers',
            'preferredSupplier',
            'options.values',
            'variants.optionValues',
            'variants.stockItems',
            'media',
        ])->findOrFail($catalogItem);

        $this->authorize('view', $record);

        return response()->json([
            'data' => new CatalogItemResource($record),
        ]);
    }

    public function update(UpdateCatalogItemRequest $request, string $catalogItem): JsonResponse
    {
        $record = CatalogItem::findOrFail($catalogItem);
        $this->authorize('update', $record);

        $item = $this->writeService->update($record, $request->validated(), $request);

        return response()->json([
            'message' => 'Catalog item updated successfully.',
            'data' => new CatalogItemResource($item),
        ]);
    }

    public function destroy(string $catalogItem): JsonResponse
    {
        $record = CatalogItem::findOrFail($catalogItem);
        $this->authorize('delete', $record);
        $this->writeService->delete($record);

        return response()->json([
            'message' => 'Catalog item deleted successfully.',
        ]);
    }

    public function generateVariants(Request $request, string $catalogItem): JsonResponse
    {
        $item = CatalogItem::findOrFail($catalogItem);
        $this->authorize('update', $item);

        $validated = $request->validate([
            'variants' => 'required|array',
            'variants.*.sku' => 'required|string|max:60',
            'variants.*.barcode' => 'nullable|string|max:100',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.cost_price' => 'nullable|numeric|min:0',
            'variants.*.option_value_ids' => 'required|array',
            'variants.*.option_value_ids.*' => 'required|string',
        ]);

        $createdVariantsCount = $this->writeService->generateVariants($item, $validated['variants']);

        return response()->json([
            'message' => "Successfully generated {$createdVariantsCount} new variants.",
        ], 201);
    }
}
