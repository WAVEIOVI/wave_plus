<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OperationalHub\Enums\WorkshopReceptionStatus;
use App\Modules\OperationalHub\Models\WorkshopReception;
use App\Modules\OperationalHub\Requests\CreateWorkshopReceptionRequest;
use App\Modules\OperationalHub\Resources\WorkshopReceptionResource;
use App\Modules\OperationalHub\Services\WorkshopReceptionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkshopReceptionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly WorkshopReceptionService $receptionService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WorkshopReception::class);

        $query = WorkshopReception::query()
            ->with(['workshopUnit', 'equipment', 'client', 'receiver', 'swo', 'sourceMission', 'exitPass']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('workshop_unit_id')) {
            $query->where('workshop_unit_id', $request->input('workshop_unit_id'));
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }
        if ($request->filled('swo_id')) {
            $query->where('swo_id', $request->input('swo_id'));
        }
        if ($request->filled('equipment_id')) {
            $query->where('equipment_id', $request->input('equipment_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('reception_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('reception_date', '<=', $request->input('date_to'));
        }

        $q = $request->input('q') ?? $request->input('search');
        if (!empty($q)) {
            $query->where(function ($sub) use ($q) {
                $sub->where('reception_number', 'LIKE', "%{$q}%")
                    ->orWhereHas('client', fn ($c) => $c->where('legal_name', 'LIKE', "%{$q}%")->orWhere('commercial_name', 'LIKE', "%{$q}%"))
                    ->orWhereHas('equipment', fn ($e) => $e->where('equipment_code', 'LIKE', "%{$q}%")->orWhere('serial_number', 'LIKE', "%{$q}%"));
            });
        }

        $perPage = (int) $request->input('itemsPerPage', $request->input('per_page', 15));
        $receptions = $query->latest()->paginate($perPage);

        return WorkshopReceptionResource::collection($receptions);
    }

    public function show(WorkshopReception $workshopReception): JsonResponse
    {
        $this->authorize('view', $workshopReception);

        $workshopReception->load(['workshopUnit', 'equipment', 'client', 'receiver', 'swo', 'sourceMission', 'exitPass']);

        return response()->json([
            'data' => new WorkshopReceptionResource($workshopReception),
        ]);
    }

    public function store(CreateWorkshopReceptionRequest $request): JsonResponse
    {
        $this->authorize('create', WorkshopReception::class);

        $reception = $this->receptionService->receiveEquipment(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Workshop reception recorded successfully.',
            'data'    => new WorkshopReceptionResource($reception),
        ], 201);
    }

    public function update(Request $request, WorkshopReception $workshopReception): JsonResponse
    {
        $this->authorize('update', $workshopReception);

        $validated = $request->validate([
            'condition'            => 'nullable|string',
            'visible_damages'      => 'nullable|string',
            'accessories_received' => 'nullable|string',
            'notes'                => 'nullable|string',
            'status'               => 'nullable|string',
        ]);

        $workshopReception->update(array_merge($validated, ['updated_by' => $request->user()?->id]));

        return response()->json([
            'message' => 'Workshop reception updated successfully.',
            'data'    => new WorkshopReceptionResource($workshopReception->fresh(['workshopUnit', 'equipment', 'client', 'sourceMission', 'exitPass'])),
        ]);
    }

    public function confirm(Request $request, WorkshopReception $workshopReception): JsonResponse
    {
        $this->authorize('update', $workshopReception);

        $reception = $this->receptionService->confirmReception(
            $workshopReception,
            $request->all(),
            $request->user()
        );

        return response()->json([
            'message' => 'Workshop reception confirmed.',
            'data'    => new WorkshopReceptionResource($reception),
        ]);
    }
}
