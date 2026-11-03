<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OperationalHub\Models\Intervention;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Requests\CreateInterventionRequest;
use App\Modules\OperationalHub\Resources\FindingResource;
use App\Modules\OperationalHub\Resources\InterventionResource;
use App\Modules\OperationalHub\Services\InterventionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InterventionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly InterventionService $interventionService,
    ) {}

    /**
     * List interventions.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Intervention::class);

        $query = Intervention::query()
            ->with(['mission.swo.client', 'equipment', 'blueprint', 'materials', 'findings']);

        if ($request->filled('mission_id')) {
            $query->where('mission_id', $request->input('mission_id'));
        }
        if ($request->filled('equipment_id')) {
            $query->where('equipment_id', $request->input('equipment_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('result')) {
            $query->where('result', $request->input('result'));
        }

        $perPage = (int) $request->input('itemsPerPage', $request->input('per_page', 15));
        $interventions = $query->latest()->paginate($perPage);

        return InterventionResource::collection($interventions);
    }

    /**
     * Show intervention.
     */
    public function show(Intervention $intervention): JsonResponse
    {
        $this->authorize('view', $intervention);

        $intervention->load(['mission.swo.client', 'equipment', 'blueprint', 'materials.catalogItem', 'findings']);

        return response()->json([
            'data' => new InterventionResource($intervention),
        ]);
    }

    /**
     * Create intervention on a mission.
     */
    public function store(CreateInterventionRequest $request, Mission $mission): JsonResponse
    {
        $this->authorize('create', Intervention::class);

        $intervention = $this->interventionService->createIntervention(
            $mission,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Intervention created successfully.',
            'data' => new InterventionResource($intervention),
        ], 201);
    }

    /**
     * Update intervention.
     */
    public function update(Request $request, Intervention $intervention): JsonResponse
    {
        $this->authorize('update', $intervention);

        $updated = $this->interventionService->updateIntervention(
            $intervention,
            $request->all(),
            $request->user()
        );

        return response()->json([
            'message' => 'Intervention updated successfully.',
            'data' => new InterventionResource($updated),
        ]);
    }

    /**
     * Complete intervention.
     */
    public function complete(Request $request, Intervention $intervention): JsonResponse
    {
        $this->authorize('update', $intervention);

        $completed = $this->interventionService->completeIntervention(
            $intervention,
            $request->all(),
            $request->user()
        );

        return response()->json([
            'message' => 'Intervention marked as completed.',
            'data' => new InterventionResource($completed),
        ]);
    }

    /**
     * Add material used to intervention.
     */
    public function addMaterial(Request $request, Intervention $intervention): JsonResponse
    {
        $this->authorize('update', $intervention);

        $request->validate([
            'catalog_item_id' => 'required|ulid|exists:catalog_items,id',
            'catalog_variant_id' => 'nullable|ulid|exists:catalog_variants,id',
            'qty_used' => 'required|numeric|gt:0',
            'description_snapshot' => 'nullable|string',
            'uom_code_snapshot' => 'nullable|string',
            'variant_sku_snapshot' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $material = $this->interventionService->addMaterial($intervention, $request->all(), $request->user());

        return response()->json([
            'message' => 'Material added successfully.',
            'data' => $material,
        ], 201);
    }

    /**
     * Add finding to intervention.
     */
    public function addFinding(Request $request, Intervention $intervention): JsonResponse
    {
        $this->authorize('update', $intervention);

        $request->validate([
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'severity' => 'nullable|in:low,medium,high,critical',
            'recommendation' => 'nullable|string',
            'requires_workshop_transfer' => 'nullable|boolean',
        ]);

        $finding = $this->interventionService->addFinding($intervention, $request->all(), $request->user());

        return response()->json([
            'message' => 'Finding recorded successfully.',
            'data' => new FindingResource($finding),
        ], 201);
    }
}
