<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\WorkshopExitPass;
use App\Modules\OperationalHub\Resources\WorkshopExitPassResource;
use App\Modules\OperationalHub\Services\WorkshopExitPassService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkshopExitPassController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly WorkshopExitPassService $exitPassService,
    ) {}

    /**
     * List all exit passes with optional filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WorkshopExitPass::class);

        $query = WorkshopExitPass::query()
            ->with(['destinationWorkshopUnit', 'responsibleEmployee', 'serviceWorkOrder', 'mission', 'equipment'])
            ->latest();

        if ($request->filled('swo_id')) {
            $query->where('swo_id', $request->input('swo_id'));
        }

        if ($request->filled('mission_id')) {
            $query->where('mission_id', $request->input('mission_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('workshop_unit_id')) {
            $query->where('destination_workshop_unit_id', $request->input('workshop_unit_id'));
        }

        $passes = $query->paginate((int) $request->input('per_page', 25));

        return WorkshopExitPassResource::collection($passes);
    }

    /**
     * List exit passes for a specific mission.
     */
    public function indexForMission(Mission $mission): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WorkshopExitPass::class);

        $passes = WorkshopExitPass::where('mission_id', $mission->id)
            ->with(['destinationWorkshopUnit', 'responsibleEmployee', 'equipment'])
            ->latest()
            ->get();

        return WorkshopExitPassResource::collection($passes);
    }

    /**
     * Create an exit pass for a specific mission.
     */
    public function storeForMission(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('create', WorkshopExitPass::class);

        $pass = $this->exitPassService->createExitPass($mission, $request->all(), $request->user());

        return response()->json([
            'message' => 'Workshop Exit Pass generated successfully.',
            'data' => new WorkshopExitPassResource($pass),
        ], 201);
    }

    /**
     * Create an exit pass generally (expects mission_id in request).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WorkshopExitPass::class);

        $request->validate([
            'mission_id' => ['ulid', 'required', 'exists:missions,id'],
        ]);

        $mission = Mission::findOrFail($request->input('mission_id'));
        $pass = $this->exitPassService->createExitPass($mission, $request->all(), $request->user());

        return response()->json([
            'message' => 'Workshop Exit Pass generated successfully.',
            'data' => new WorkshopExitPassResource($pass),
        ], 201);
    }

    /**
     * Show a single exit pass.
     */
    public function show(WorkshopExitPass $exitPass): WorkshopExitPassResource
    {
        $this->authorize('view', $exitPass);

        $exitPass->loadMissing(['destinationWorkshopUnit', 'responsibleEmployee', 'serviceWorkOrder', 'mission', 'equipment']);

        return new WorkshopExitPassResource($exitPass);
    }

    /**
     * Update exit pass status (e.g. pending -> confirmed -> received -> returned).
     */
    public function updateStatus(Request $request, WorkshopExitPass $exitPass): JsonResponse
    {
        $this->authorize('update', $exitPass);

        $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,received,returned,cancelled'],
        ]);

        $updated = $this->exitPassService->updateStatus($exitPass, $request->input('status'), $request->user());

        return response()->json([
            'message' => "Exit Pass status updated to {$updated->status}.",
            'data' => new WorkshopExitPassResource($updated),
        ]);
    }

    /**
     * Transfer stats for a mission (transferred vs available equipment).
     */
    public function transferStats(Mission $mission): JsonResponse
    {
        $this->authorize('viewAny', WorkshopExitPass::class);

        $stats = $this->exitPassService->getTransferStats($mission);

        return response()->json(['data' => $stats]);
    }
}
