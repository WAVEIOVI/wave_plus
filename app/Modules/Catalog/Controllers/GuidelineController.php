<?php

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Controllers\Concerns\AuthorizesCatalogRequests;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\Catalog\Models\Guideline;
use App\Modules\Catalog\Models\GuidelineStep;
use App\Modules\Catalog\Requests\StoreGuidelineRequest;
use App\Modules\Catalog\Requests\StoreGuidelineStepRequest;
use App\Modules\Catalog\Requests\UpdateGuidelineRequest;
use App\Modules\Catalog\Requests\UpdateGuidelineStepRequest;
use App\Modules\Catalog\Resources\GuidelineResource;
use App\Modules\Catalog\Resources\GuidelineStepResource;
use App\Modules\Catalog\Services\CatalogQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuidelineController extends Controller
{
    use AuthorizesCatalogRequests;

    public function __construct(private readonly CatalogQueryService $queryService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Guideline::class);

        $query = Guideline::withCount('steps')
            ->orderBy('id');

        $this->queryService->applySearch($query, $request, ['name', 'code']);

        if ($request->has('filter.is_active')) {
            $query->where('is_active', $request->boolean('filter.is_active'));
        }

        return $this->queryService->paginate($query, $request, GuidelineResource::class);
    }

    public function store(StoreGuidelineRequest $request): JsonResponse
    {
        $this->authorize('create', Guideline::class);

        $guideline = Guideline::create($request->validated());

        return response()->json([
            'message' => 'Guideline created successfully.',
            'data' => new GuidelineResource($guideline),
        ], 201);
    }

    public function show(string $guideline): JsonResponse
    {
        $record = Guideline::with('steps')->findOrFail($guideline);
        $this->authorize('view', $record);

        return response()->json([
            'data' => new GuidelineResource($record),
        ]);
    }

    public function update(UpdateGuidelineRequest $request, string $guideline): JsonResponse
    {
        $record = Guideline::findOrFail($guideline);
        $this->authorize('update', $record);
        $record->update($request->validated());

        return response()->json([
            'message' => 'Guideline updated successfully.',
            'data' => new GuidelineResource($record->fresh()->load('steps')),
        ]);
    }

    public function destroy(string $guideline): JsonResponse
    {
        $record = Guideline::findOrFail($guideline);
        $this->authorize('delete', $record);
        $record->delete();

        return response()->json(['message' => 'Guideline deleted successfully.']);
    }

    // ── Guideline Steps ────────────────────────────────────────

    public function storeStep(StoreGuidelineStepRequest $request): JsonResponse
    {
        $guideline = Guideline::findOrFail($request->validated('guideline_id'));
        $this->authorize('update', $guideline);

        $step = GuidelineStep::create($request->validated());

        return response()->json([
            'message' => 'Step created successfully.',
            'data' => new GuidelineStepResource($step),
        ], 201);
    }

    public function updateStep(UpdateGuidelineStepRequest $request, string $step): JsonResponse
    {
        $record = GuidelineStep::findOrFail($step);
        $this->authorize('update', $record->guideline);
        $record->update($request->validated());

        return response()->json([
            'message' => 'Step updated successfully.',
            'data' => new GuidelineStepResource($record->fresh()),
        ]);
    }

    public function destroyStep(string $step): JsonResponse
    {
        $record = GuidelineStep::findOrFail($step);
        $this->authorize('update', $record->guideline);
        $record->delete();

        return response()->json(['message' => 'Step deleted successfully.']);
    }

    // ── Blueprint ↔ Guideline Attach/Detach ─────────────────────

    public function blueprintGuidelines(string $blueprintId): JsonResponse
    {
        $blueprint = EquipmentBlueprint::findOrFail($blueprintId);
        $this->authorize('view', $blueprint);
        $guidelines = $blueprint->guidelines()->with('steps')->get();

        return response()->json([
            'data' => GuidelineResource::collection($guidelines),
        ]);
    }

    public function attachToBlueprint(string $blueprintId, string $guidelineId): JsonResponse
    {
        $blueprint = EquipmentBlueprint::findOrFail($blueprintId);
        $this->authorize('update', $blueprint);
        $blueprint->guidelines()->syncWithoutDetaching([$guidelineId]);

        return response()->json(['message' => 'Guideline attached to blueprint.']);
    }

    public function detachFromBlueprint(string $blueprintId, string $guidelineId): JsonResponse
    {
        $blueprint = EquipmentBlueprint::findOrFail($blueprintId);
        $this->authorize('update', $blueprint);
        $blueprint->guidelines()->detach($guidelineId);

        return response()->json(['message' => 'Guideline detached from blueprint.']);
    }

    /**
     * Reorder guideline steps.
     */
    public function reorderSteps(Request $request, Guideline $guideline): JsonResponse
    {
        $this->authorize('update', $guideline);

        $validated = $request->validate([
            'steps' => 'required|array',
            'steps.*.id' => 'required|exists:guideline_steps,id',
            'steps.*.order' => 'required|integer|min:0',
        ]);

        foreach ($validated['steps'] as $stepData) {
            GuidelineStep::where('id', $stepData['id'])
                ->where('guideline_id', $guideline->id)
                ->update(['order' => $stepData['order']]);
        }

        return response()->json([
            'message' => 'Steps reordered successfully.',
            'data' => new GuidelineResource($guideline->fresh(['steps'])),
        ]);
    }

}