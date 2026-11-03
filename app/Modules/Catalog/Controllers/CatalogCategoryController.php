<?php

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Controllers\Concerns\AuthorizesCatalogRequests;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Requests\StoreCatalogCategoryRequest;
use App\Modules\Catalog\Requests\UpdateCatalogCategoryRequest;
use App\Modules\Catalog\Resources\CatalogCategoryResource;
use App\Modules\Catalog\Services\CatalogQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogCategoryController extends Controller
{
    use AuthorizesCatalogRequests;

    public function __construct(private readonly CatalogQueryService $queryService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CatalogCategory::class);

        $query = CatalogCategory::with('childrenRecursive')
            ->when($request->boolean('roots_only'), fn ($q) => $q->whereNull('parent_id'))
            ->orderBy('sort_order');

        $this->queryService->applySearch($query, $request, ['name']);

        return $this->queryService->lookup($query, $request, CatalogCategoryResource::class);
    }

    public function store(StoreCatalogCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', CatalogCategory::class);

        $category = CatalogCategory::create($request->validated());

        return response()->json([
            'message' => 'Category created successfully.',
            'data' => new CatalogCategoryResource($category),
        ], 201);
    }

    public function show(string $category): JsonResponse
    {
        $record = CatalogCategory::with(['parent', 'children'])->findOrFail($category);
        $this->authorize('view', $record);

        return response()->json([
            'data' => new CatalogCategoryResource($record),
        ]);
    }

    public function update(UpdateCatalogCategoryRequest $request, string $category): JsonResponse
    {
        $record = CatalogCategory::findOrFail($category);
        $this->authorize('update', $record);
        $record->update($request->validated());

        return response()->json([
            'message' => 'Category updated successfully.',
            'data' => new CatalogCategoryResource($record->fresh()),
        ]);
    }

    public function destroy(string $category): JsonResponse
    {
        $record = CatalogCategory::findOrFail($category);
        $this->authorize('delete', $record);
        $record->delete();

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
