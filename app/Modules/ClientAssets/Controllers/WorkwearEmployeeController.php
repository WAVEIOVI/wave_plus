<?php

namespace App\Modules\ClientAssets\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ClientAssets\Controllers\Concerns\AuthorizesClientAssetsRequests;
use App\Modules\ClientAssets\Models\WorkwearAssignment;
use App\Modules\ClientAssets\Models\WorkwearEmployee;
use App\Modules\ClientAssets\Requests\BatchStoreWorkwearEmployeeRequest;
use App\Modules\ClientAssets\Requests\StoreWorkwearEmployeeRequest;
use App\Modules\ClientAssets\Requests\UpdateWorkwearEmployeeRequest;
use App\Modules\ClientAssets\Resources\WorkwearAssignmentResource;
use App\Modules\ClientAssets\Resources\WorkwearEmployeeModelResource;
use App\Modules\ClientAssets\Resources\WorkwearEmployeeResource;
use App\Modules\ClientAssets\Services\ClientAssetsQueryService;
use App\Modules\ClientAssets\Services\WorkwearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkwearEmployeeController extends Controller
{
    use AuthorizesClientAssetsRequests;

    private const ALLOWED_SORT_COLUMNS = ['last_name', 'first_name', 'created_at', 'employee_ref', 'status'];

    public function __construct(
        private readonly ClientAssetsQueryService $queryService,
        private readonly WorkwearService $workwearService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkwearEmployee::class);

        $query = WorkwearEmployee::with('client')->withCount(['assignments', 'activeAssignments', 'activeModelAssignments']);
        if ($s = $request->input('q')) {
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('employee_ref', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"));
        }
        if ($c = $request->input('filter.client_id')) {
            $query->where('client_id', $c);
        }
        if ($st = $request->input('filter.status')) {
            $query->where('status', $st);
        }
        if ($dept = $request->input('filter.department')) {
            $query->where('department', $dept);
        }
        if ($role = $request->input('filter.role')) {
            $query->where('role', $role);
        }
        $query->orderBy(
            $this->queryService->safeSort($request, self::ALLOWED_SORT_COLUMNS, 'last_name'),
            $this->queryService->sortDirection($request, 'asc')
        );
        $p = $query->paginate($this->queryService->cappedPerPage($request));

        return response()->json(['data' => WorkwearEmployeeResource::collection($p->items()), 'total' => $p->total()]);
    }

    public function show(string $id): JsonResponse
    {
        $e = WorkwearEmployee::with([
            'client',
            'assignments.item.workwearModel', 'assignments.assignedByUser', 'assignments.returnedByUser',
        ])->withCount(['assignments', 'activeAssignments', 'activeModelAssignments'])->findOrFail($id);
        $this->authorize('view', $e);
        $this->loadModelAssignments($e);

        return response()->json(['data' => new WorkwearEmployeeResource($e)]);
    }

    public function store(StoreWorkwearEmployeeRequest $r): JsonResponse
    {
        $this->authorize('create', WorkwearEmployee::class);

        $validated = $r->validated();
        $modelAssignments = $validated['model_assignments'] ?? null;
        unset($validated['model_assignments']);

        $e = DB::transaction(function () use ($validated, $modelAssignments) {
            $e = WorkwearEmployee::create($validated);

            if (! empty($modelAssignments)) {
                $this->workwearService->syncEmployeeModels($e, $modelAssignments);
            }

            return $e;
        });

        $e->load('client');
        $this->loadModelAssignments($e);

        return response()->json(['message' => 'Employee created.', 'data' => new WorkwearEmployeeResource($e)], 201);
    }

    /**
     * Load model entitlements (assignment) and how many physical items of each model
     * the employee currently holds (issuance), so the two are never conflated.
     */
    private function loadModelAssignments(WorkwearEmployee $e): void
    {
        $e->load(['modelAssignments.workwearModel', 'modelAssignments.assignedByUser']);
        $e->loadCount('activeModelAssignments');

        $issued = WorkwearAssignment::query()
            ->join('workwear_items as wi', 'wi.id', '=', 'workwear_assignments.item_id')
            ->where('workwear_assignments.employee_id', $e->id)
            ->whereNull('workwear_assignments.returned_at')
            ->selectRaw('wi.model_id as model_id, COUNT(*) as aggregate')
            ->groupBy('wi.model_id')
            ->pluck('aggregate', 'model_id');

        foreach ($e->modelAssignments as $assignment) {
            $assignment->issued_count = (int) ($issued[$assignment->model_id] ?? 0);
        }
    }

    public function batchStore(BatchStoreWorkwearEmployeeRequest $r): JsonResponse
    {
        $this->authorize('create', WorkwearEmployee::class);

        $result = $this->workwearService->batchCreateEmployees($r->validated());

        return response()->json([
            'message' => count($result['created']).' employee(s) created.',
            'data' => WorkwearEmployeeResource::collection($result['created']),
            'errors' => $result['errors'],
        ], count($result['errors']) > 0 ? 207 : 201);
    }

    public function update(UpdateWorkwearEmployeeRequest $r, string $id): JsonResponse
    {
        $e = WorkwearEmployee::findOrFail($id);
        $this->authorize('update', $e);
        $validated = $r->validated();
        $modelAssignments = $validated['model_assignments'] ?? null;
        unset($validated['model_assignments']);

        DB::transaction(function () use ($e, $validated, $modelAssignments) {
            $e->update($validated);

            // null = untouched; [] = end every active model assignment.
            if ($modelAssignments !== null) {
                $this->workwearService->syncEmployeeModels($e, $modelAssignments);
            }
        });

        $e->load('client');
        $this->loadModelAssignments($e);

        return response()->json(['message' => 'Employee updated.', 'data' => new WorkwearEmployeeResource($e)]);
    }

    public function destroy(string $id): JsonResponse
    {
        $e = WorkwearEmployee::findOrFail($id);
        $this->authorize('delete', $e);

        if ($e->activeAssignments()->exists()) {
            return response()->json(['message' => 'Cannot delete: employee has active workwear assignments.'], 422);
        }

        $e->delete();

        return response()->json(['message' => 'Employee deleted.']);
    }

    public function history(string $id): JsonResponse
    {
        $e = WorkwearEmployee::with(['assignments.item.workwearModel', 'assignments.assignedByUser', 'assignments.returnedByUser'])->findOrFail($id);
        $this->authorize('view', $e);
        $this->loadModelAssignments($e);

        return response()->json(['data' => [
            'employee' => new WorkwearEmployeeResource($e),
            'history' => WorkwearAssignmentResource::collection($e->assignments),
            'model_history' => WorkwearEmployeeModelResource::collection($e->modelAssignments),
        ]]);
    }
}
