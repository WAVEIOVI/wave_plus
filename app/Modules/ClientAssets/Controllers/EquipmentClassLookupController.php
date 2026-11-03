<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ClientAssets\Controllers\Concerns\AuthorizesClientAssetsRequests;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\Catalog\Models\EquipmentClass;
use App\Modules\Catalog\Resources\EquipmentClassResource;
use App\Modules\Catalog\Services\CatalogQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only Catalog equipment-class lookup for Client Assets forms.
 * Writes remain on /api/catalog/equipment-classes.
 */
class EquipmentClassLookupController extends Controller
{
    use AuthorizesClientAssetsRequests;

    public function __construct(private readonly CatalogQueryService $queryService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ClientEquipment::class);

        return $this->queryService->paginate(
            $this->queryService->equipmentClassListQuery($request),
            $request,
            EquipmentClassResource::class
        );
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('viewAny', ClientEquipment::class);

        $class = EquipmentClass::with(['blueprints.blueprintOptions.values'])
            ->withCount(['blueprints', 'equipment'])
            ->findOrFail($id);

        return response()->json(['data' => new EquipmentClassResource($class)]);
    }
}
