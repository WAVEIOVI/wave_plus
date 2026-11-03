<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Controllers\Concerns\AuthorizesCatalogRequests;
use App\Modules\Catalog\Models\EquipmentClass;
use App\Modules\Catalog\Resources\EquipmentClassResource;
use App\Modules\Catalog\Services\CatalogQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EquipmentClassController extends Controller
{
    use AuthorizesCatalogRequests;

    public function __construct(private readonly CatalogQueryService $queryService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EquipmentClass::class);

        return $this->queryService->paginate(
            $this->queryService->equipmentClassListQuery($request),
            $request,
            EquipmentClassResource::class
        );
    }

    public function show(string $id): JsonResponse
    {
        $class = EquipmentClass::with(['blueprints.blueprintOptions.values'])
            ->withCount(['blueprints', 'equipment'])
            ->findOrFail($id);

        $this->authorize('view', $class);

        return response()->json(['data' => new EquipmentClassResource($class)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EquipmentClass::class);

        $validated = $request->validate([
            'code' => 'required|string|max:60|unique:equipment_classes,code',
            'name' => 'required|array',
            'description' => 'nullable|array',
            'parent_id' => 'nullable|ulid|exists:equipment_classes,id',
            'status' => 'nullable|string|in:active,inactive',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $class = EquipmentClass::create($validated);

        return response()->json([
            'message' => 'Equipment class created successfully.',
            'data' => new EquipmentClassResource($class),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $class = EquipmentClass::findOrFail($id);
        $this->authorize('update', $class);

        $validated = $request->validate([
            'code' => "sometimes|required|string|max:60|unique:equipment_classes,code,{$id}",
            'name' => 'sometimes|required|array',
            'description' => 'nullable|array',
            'parent_id' => 'nullable|ulid|exists:equipment_classes,id',
            'status' => 'nullable|string|in:active,inactive',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['updated_by'] = Auth::id();
        $class->update($validated);

        return response()->json([
            'message' => 'Equipment class updated successfully.',
            'data' => new EquipmentClassResource($class),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $class = EquipmentClass::findOrFail($id);
        $this->authorize('delete', $class);

        if ($class->blueprints()->exists() || $class->equipment()->exists()) {
            return response()->json([
                'message' => 'Cannot delete equipment class: it is linked to blueprints or equipment.',
            ], 422);
        }

        $class->delete();

        return response()->json(['message' => 'Equipment class deleted successfully.']);
    }
}
