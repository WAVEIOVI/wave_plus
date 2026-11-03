<?php

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Controllers\Concerns\AuthorizesCatalogRequests;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Catalog\Requests\StoreUnitOfMeasureRequest;
use App\Modules\Catalog\Requests\UpdateUnitOfMeasureRequest;
use App\Modules\Catalog\Resources\UnitOfMeasureResource;
use App\Modules\Catalog\Services\CatalogQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitOfMeasureController extends Controller
{
    use AuthorizesCatalogRequests;

    public function __construct(private readonly CatalogQueryService $queryService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UnitOfMeasure::class);

        $query = UnitOfMeasure::orderBy('code');

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $this->queryService->applySearch($query, $request, ['code', 'name']);

        return $this->queryService->lookup($query, $request, UnitOfMeasureResource::class);
    }

    public function store(StoreUnitOfMeasureRequest $request): JsonResponse
    {
        $this->authorize('create', UnitOfMeasure::class);

        $uom = UnitOfMeasure::create($request->validated());

        return response()->json([
            'message' => 'Unit of measure created successfully.',
            'data' => new UnitOfMeasureResource($uom),
        ], 201);
    }

    public function show(string $unitOfMeasure): JsonResponse
    {
        $record = UnitOfMeasure::findOrFail($unitOfMeasure);
        $this->authorize('view', $record);

        return response()->json([
            'data' => new UnitOfMeasureResource($record),
        ]);
    }

    public function update(UpdateUnitOfMeasureRequest $request, string $unitOfMeasure): JsonResponse
    {
        $record = UnitOfMeasure::findOrFail($unitOfMeasure);
        $this->authorize('update', $record);
        $record->update($request->validated());

        return response()->json([
            'message' => 'Unit of measure updated successfully.',
            'data' => new UnitOfMeasureResource($record->fresh()),
        ]);
    }

    public function destroy(string $unitOfMeasure): JsonResponse
    {
        $record = UnitOfMeasure::findOrFail($unitOfMeasure);
        $this->authorize('delete', $record);
        $record->delete();

        return response()->json(['message' => 'Unit of measure deleted successfully.']);
    }
}
