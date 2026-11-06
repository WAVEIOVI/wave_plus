<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OperationalHub\Enums\MissionStatus;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\OperationalHub\Requests\CreateMissionRequest;
use App\Modules\OperationalHub\Requests\CreateWorkshopTransferRequest;
use App\Modules\OperationalHub\Resources\FindingResource;
use App\Modules\OperationalHub\Resources\InterventionResource;
use App\Modules\OperationalHub\Resources\MissionListResource;
use App\Modules\OperationalHub\Resources\MissionResource;
use App\Modules\OperationalHub\Resources\ServiceWorkOrderResource;
use App\Modules\OperationalHub\Services\MissionService;
use App\Modules\OperationalHub\Services\WorkshopTransferService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MissionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly MissionService $missionService,
        private readonly WorkshopTransferService $transferService,
    ) {}

    /**
     * Paginated index of missions.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Mission::class);

        $query = Mission::query()
            ->with(['swo.client', 'team', 'leadTechnician', 'workshopUnit', 'serviceSite'])
            ->withCount('interventions');

        if ($request->filled('mode')) {
            $query->where('mode', $request->input('mode'));
        }

        if ($request->filled('status')) {
            $statuses = array_filter(array_map('trim', explode(',', $request->input('status'))));
            if (count($statuses) === 1) {
                $query->where('status', $statuses[0]);
            } elseif (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            }
        }

        if ($request->filled('swo_id')) {
            $query->where('swo_id', $request->input('swo_id'));
        }
        if ($request->filled('workshop_unit_id')) {
            $query->where('workshop_unit_id', $request->input('workshop_unit_id'));
        }
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->input('team_id'));
        }
        if ($request->filled('lead_technician_id')) {
            $query->where('lead_technician_id', $request->input('lead_technician_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('planned_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('planned_date', '<=', $request->input('date_to'));
        }

        $q = $request->input('q') ?? $request->input('search');
        if (! empty($q)) {
            $query->where(function ($sub) use ($q) {
                $sub->where('mission_number', 'LIKE', "%{$q}%")
                    ->orWhereHas('swo', fn ($s) => $s->where('swo_number', 'LIKE', "%{$q}%"))
                    ->orWhereHas('swo.client', fn ($c) => $c->where('legal_name', 'LIKE', "%{$q}%")->orWhere('commercial_name', 'LIKE', "%{$q}%"));
            });
        }

        // Sorting
        $allowedSortFields = ['planned_date', 'mission_number', 'created_at', 'status'];
        $sortBy = $request->input('sortBy', 'planned_date');
        $sortBy = in_array($sortBy, $allowedSortFields, true) ? $sortBy : 'planned_date';
        
        $orderBy = $request->input('orderBy', 'desc');
        $orderBy = in_array($orderBy, ['asc', 'desc'], true) ? $orderBy : 'desc';
        
        $query->orderBy($sortBy, $orderBy);

        $perPage = (int) $request->input('itemsPerPage', $request->input('per_page', 15));
        $missions = $query->paginate($perPage);

        return MissionListResource::collection($missions);
    }

    /**
     * Show mission detail.
     */
    public function show(Mission $mission): JsonResponse
    {
        $this->authorize('view', $mission);

        $mission->load([
            'swo.client',
            'team',
            'leadTechnician',
            'workshopUnit',
            'serviceSite',
            'vehicle',
            'employees',
            'attendances.employee',
            'checklists.items',
            'interventions.equipment',
            'interventions.blueprint',
            'interventions.materials',
            'findings',
            'exitPasses.destinationWorkshopUnit',
            'exitPasses.equipment',
        ]);

        return response()->json([
            'data' => new MissionResource($mission),
        ]);
    }

    /**
     * Create mission.
     */
    public function store(CreateMissionRequest $request): JsonResponse
    {
        $this->authorize('create', Mission::class);

        $swo = ServiceWorkOrder::findOrFail($request->input('swo_id'));

        $mission = $this->missionService->createMission(
            $swo,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Mission created successfully.',
            'data' => new MissionResource($mission->load(['team', 'leadTechnician', 'workshopUnit'])),
        ], 201);
    }

    /**
     * Update mission.
     */
    public function update(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('update', $mission);

        $fields = $request->validate([
            'planned_date' => 'nullable|date',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'planned_start_time' => 'nullable|string',
            'planned_duration_hours' => 'nullable|numeric|gte:0',
            'preparation_notes' => 'nullable|string',
            'client_contact_name' => 'nullable|string',
            'team_id' => 'nullable|ulid|exists:hr_work_teams,id',
            'lead_technician_id' => 'nullable|ulid|exists:hr_employees,id',
            'workshop_unit_id' => 'nullable|ulid|exists:workshop_units,id',
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
        ]);

        $mission->update(array_merge($fields, ['updated_by' => $request->user()?->id]));

        return response()->json([
            'message' => 'Mission updated successfully.',
            'data' => new MissionResource($mission->fresh(['team', 'leadTechnician', 'workshopUnit', 'vehicle'])),
        ]);
    }

    /**
     * Transition mission status.
     */
    public function transition(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('update', $mission);

        $request->validate([
            'status' => 'required|string',
            'notes' => 'nullable|string',
            'completion_notes' => 'nullable|string',
            'client_signature' => 'nullable|string',
            'client_satisfaction' => 'nullable|string',
        ]);

        $targetStatus = MissionStatus::from($request->input('status'));

        $updated = $this->missionService->transition(
            $mission,
            $targetStatus,
            $request->user(),
            $request->all()
        );

        return response()->json([
            'message' => "Mission transitioned to {$targetStatus->label()}.",
            'data' => new MissionResource($updated),
        ]);
    }

    /**
     * Record/update attendance for this mission.
     */
    public function recordAttendance(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('update', $mission);

        $request->validate([
            'attendances' => 'required|array',
            'attendances.*.employee_id' => 'required|string|exists:hr_employees,id',
            'attendances.*.date' => 'nullable|date',
            'attendances.*.arrival_time' => 'nullable|string',
            'attendances.*.departure_time' => 'nullable|string',
            'attendances.*.status' => 'nullable|in:present,absent,late,partial,left_early',
            'attendances.*.notes' => 'nullable|string',
        ]);

        $this->missionService->recordAttendance($mission, $request->input('attendances'), $request->user());

        return response()->json([
            'message' => 'Attendance recorded successfully.',
            'data' => $mission->fresh('attendances.employee')->attendances,
        ]);
    }

    /**
     * Save/update mission checklist.
     */
    public function saveChecklist(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('update', $mission);

        $request->validate([
            'title' => 'nullable|string',
            'items' => 'required|array',
            'items.*.title' => 'required|string',
            'items.*.answer' => 'nullable|in:yes,no,na',
            'items.*.is_completed' => 'nullable|boolean',
            'items.*.notes' => 'nullable|string',
            'items.*.evidence_path' => 'nullable|string',
        ]);

        $checklist = $this->missionService->saveChecklist(
            $mission,
            $request->input('items'),
            $request->input('title', 'Pre-operation Checklist'),
            $request->user()
        );

        return response()->json([
            'message' => 'Checklist saved successfully.',
            'data' => $checklist,
        ]);
    }

    /**
     * Get interventions of this mission.
     */
    public function interventions(Mission $mission): AnonymousResourceCollection
    {
        $this->authorize('view', $mission);

        $interventions = $mission->interventions()
            ->with(['equipment', 'blueprint', 'materials', 'findings'])
            ->latest()
            ->get();

        return InterventionResource::collection($interventions);
    }

    /**
     * Get findings of this mission.
     */
    public function findings(Mission $mission): AnonymousResourceCollection
    {
        $this->authorize('view', $mission);

        return FindingResource::collection($mission->findings()->latest()->get());
    }

    /**
     * Initiate workshop transfer from a field mission.
     */
    public function workshopTransfer(CreateWorkshopTransferRequest $request, Mission $mission): JsonResponse
    {
        $this->authorize('update', $mission);

        $result = $this->transferService->createWorkshopSwoFromFieldMission(
            $mission,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Workshop transfer initiated successfully. A draft Workshop SWO was created; equipment must still be received at the workshop.',
            'data' => [
                'workshop_swo' => new ServiceWorkOrderResource($result['workshop_swo']),
            ],
        ], 201);
    }

    /**
     * Operational Scope / Equipment Explorer feed for this mission.
     */
    public function scope(Mission $mission): JsonResponse
    {
        $this->authorize('view', $mission);

        $swo = $mission->swo()->firstOrFail();

        return response()->json([
            'data' => app(\App\Modules\OperationalHub\Services\SwoService::class)
                ->getOperationalScope($swo, $mission->id),
        ]);
    }

    /**
     * Get operational summary statistics for Mission view.
     */
    public function stats(Mission $mission): JsonResponse
    {
        $this->authorize('view', $mission);
        $stats = $this->missionService->getMissionStats($mission);

        return response()->json(['data' => $stats]);
    }

    /**
     * Get technicians assigned to mission.
     */
    public function employees(Mission $mission): JsonResponse
    {
        $this->authorize('view', $mission);
        $employees = $mission->missionEmployees()->with('employee')->get();

        return response()->json(['data' => $employees]);
    }

    /**
     * Update/sync technicians assigned to mission.
     */
    public function updateEmployees(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('update', $mission);
        $employeeIds = $request->input('employee_ids', []);
        $leadId = $request->input('lead_employee_id');
        $this->missionService->attachEmployees($mission, $employeeIds, $leadId);

        return response()->json([
            'message' => 'Technicians updated successfully.',
            'data' => $mission->missionEmployees()->with('employee')->get(),
        ]);
    }
}
