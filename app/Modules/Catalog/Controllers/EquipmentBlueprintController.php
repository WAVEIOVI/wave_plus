<?php

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Controllers\Concerns\AuthorizesCatalogRequests;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\Catalog\Requests\StoreEquipmentBlueprintRequest;
use App\Modules\Catalog\Requests\UpdateEquipmentBlueprintRequest;
use App\Modules\Catalog\Resources\EquipmentBlueprintResource;
use App\Modules\Catalog\Services\BlueprintCalculationService;
use App\Modules\Catalog\Services\CatalogQueryService;
use App\Modules\Catalog\Services\EquipmentBlueprintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class EquipmentBlueprintController extends Controller
{
    use AuthorizesCatalogRequests;

    public function __construct(
        private readonly CatalogQueryService $queryService,
        private readonly EquipmentBlueprintService $blueprintService,
        private readonly BlueprintCalculationService $calculationService,
    ) {}

    /**
     * Shared eager-load array used by show / store‐response / update‐response.
     */
    private function eagerLoads(): array
    {
        return [
            'equipmentClass',
            'guidelines',
            'blueprintOptions.values',
            'interventionCycles',
            'servicingItems.servicingItem',
            'servicingItems.consumables.consumableItem',
            'servicingItems.consumables.calculationSourceOption',
        ];
    }

    // ─── LIST ────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EquipmentBlueprint::class);

        $query = QueryBuilder::for(EquipmentBlueprint::with(['equipmentClass'])->withCount(['blueprintOptions', 'servicingItems']))
            ->allowedFilters([
                AllowedFilter::partial('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('equipment_class_id'),
            ])
            ->allowedSorts([
                AllowedSort::field('id'),
                AllowedSort::field('code'),
                AllowedSort::field('equipment_class_id'),
                AllowedSort::field('created_at'),
            ])
            ->allowedIncludes([
                'equipmentClass',
                'guidelines',
                'blueprintOptions.values',
                'interventionCycles',
                'servicingItems.servicingItem',
                'servicingItems.consumables.consumableItem',
                'servicingItems.consumables.calculationSourceOption',
            ])
            ->defaultSort('-created_at');

        $this->queryService->applySearch($query, $request, ['code', 'name']);

        return $this->queryService->paginate($query, $request, EquipmentBlueprintResource::class);
    }

    // ─── SHOW ────────────────────────────────────────────────────────

    public function show(string $equipmentBlueprint): JsonResponse
    {
        $record = EquipmentBlueprint::with($this->eagerLoads())
            ->findOrFail($equipmentBlueprint);
        $this->authorize('view', $record);

        return response()->json([
            'data' => new EquipmentBlueprintResource($record),
        ]);
    }

    // ─── STORE ───────────────────────────────────────────────────────

    public function store(StoreEquipmentBlueprintRequest $request): JsonResponse
    {
        $this->authorize('create', EquipmentBlueprint::class);

        $blueprint = $this->blueprintService->create($request->validated());

        return response()->json([
            'message' => 'Equipment blueprint created successfully.',
            'data' => new EquipmentBlueprintResource(
                $blueprint->load($this->eagerLoads())
            ),
        ], 201);
    }

    // ─── UPDATE ──────────────────────────────────────────────────────

    public function update(UpdateEquipmentBlueprintRequest $request, string $equipmentBlueprint): JsonResponse
    {
        $record = EquipmentBlueprint::findOrFail($equipmentBlueprint);
        $this->authorize('update', $record);

        $updated = $this->blueprintService->update($record, $request->validated());

        return response()->json([
            'message' => 'Equipment blueprint updated successfully.',
            'data' => new EquipmentBlueprintResource(
                $updated->load($this->eagerLoads())
            ),
        ]);
    }

    // ─── DESTROY ─────────────────────────────────────────────────────

    public function destroy(string $equipmentBlueprint): JsonResponse
    {
        $record = EquipmentBlueprint::findOrFail($equipmentBlueprint);
        $this->authorize('delete', $record);

        $this->blueprintService->delete($record);

        return response()->json(['message' => 'Equipment blueprint deleted successfully.']);
    }

    // ─── PREVIEW ─────────────────────────────────────────────────────

    public function preview(Request $request, string $equipmentBlueprint): JsonResponse
    {
        $record = EquipmentBlueprint::findOrFail($equipmentBlueprint);
        $this->authorize('view', $record);

        $validated = $request->validate([
            'selected_option_values' => 'nullable|array',
            'selected_option_values.*' => 'string',
            'locale' => 'nullable|string|max:10',
        ]);

        $selectedOptionValues = $validated['selected_option_values'] ?? [];
        $locale = $validated['locale'] ?? app()->getLocale();

        try {
            $previewData = $this->calculationService->preview($record, $selectedOptionValues, $locale);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            // Log the exception and return a generic error
            \Log::error($e);
            return response()->json([
                'message' => 'An error occurred while previewing the blueprint.',
            ], 500);
        }

        return response()->json([
            'data' => $previewData,
        ]);
    }
}
