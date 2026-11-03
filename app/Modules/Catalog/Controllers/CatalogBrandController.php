<?php

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Controllers\Concerns\AuthorizesCatalogRequests;
use App\Modules\Catalog\Models\CatalogBrand;
use App\Modules\Catalog\Requests\StoreCatalogBrandRequest;
use App\Modules\Catalog\Requests\UpdateCatalogBrandRequest;
use App\Modules\Catalog\Resources\CatalogBrandResource;
use App\Modules\Catalog\Services\CatalogQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogBrandController extends Controller
{
    use AuthorizesCatalogRequests;

    public function __construct(private readonly CatalogQueryService $queryService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CatalogBrand::class);

        $query = CatalogBrand::orderBy('name');
        $this->queryService->applySearch($query, $request, ['name', 'code']);

        return $this->queryService->lookup($query, $request, CatalogBrandResource::class);
    }

    public function store(StoreCatalogBrandRequest $request): JsonResponse
    {
        $this->authorize('create', CatalogBrand::class);

        $data = $request->validated();
        unset($data['logo']);

        $brand = CatalogBrand::create($data);

        $this->syncLogoMedia($brand, $request);

        return response()->json([
            'message' => 'Brand created successfully.',
            'data' => new CatalogBrandResource($brand->fresh()),
        ], 201);
    }

    public function show(string $brand): JsonResponse
    {
        $record = CatalogBrand::findOrFail($brand);
        $this->authorize('view', $record);

        return response()->json([
            'data' => new CatalogBrandResource($record),
        ]);
    }

    public function update(UpdateCatalogBrandRequest $request, string $brand): JsonResponse
    {
        $record = CatalogBrand::findOrFail($brand);
        $this->authorize('update', $record);

        $data = $request->validated();
        unset($data['logo']);

        $record->update($data);

        $this->syncLogoMedia($record, $request);

        return response()->json([
            'message' => 'Brand updated successfully.',
            'data' => new CatalogBrandResource($record->fresh()),
        ]);
    }

    public function destroy(string $brand): JsonResponse
    {
        $record = CatalogBrand::findOrFail($brand);
        $this->authorize('delete', $record);
        $record->delete();

        return response()->json(['message' => 'Brand deleted successfully.']);
    }

    /**
     * Synchronize logo using the shared BrandingSyncService so CatalogBrand
     * and Business Partners behave identically.
     */
    protected function syncLogoMedia(CatalogBrand $brand, Request $request): void
    {
        \App\Modules\Shared\Services\BrandingSyncService::syncLogo($brand, $request);
    }
}

