<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ClientAssets\Controllers\Concerns\AuthorizesClientAssetsRequests;
use App\Modules\ClientAssets\Models\WorkwearFabric;
use App\Modules\ClientAssets\Models\WorkwearModel;
use App\Modules\ClientAssets\Resources\WorkwearFabricResource;
use App\Modules\ClientAssets\Services\ClientAssetsQueryService;
use Database\Seeders\WorkwearFabricSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class WorkwearFabricController extends Controller
{
    use AuthorizesClientAssetsRequests;

    private const ALLOWED_SORT_COLUMNS = ['sort_order', 'code', 'name', 'weight_gsm', 'created_at'];

    public function __construct(
        private readonly ClientAssetsQueryService $queryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkwearModel::class);

        if (! Schema::hasTable('workwear_fabrics')) {
            return response()->json([
                'data' => [],
                'total' => 0,
            ]);
        }

        if ($request->boolean('seed_if_empty') && WorkwearFabric::withTrashed()->count() === 0) {
            (new WorkwearFabricSeeder)->run();
        }

        $query = WorkwearFabric::query()->withCount('workwearModels');

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('composition', 'like', "%{$search}%")
                    ->orWhere('weave_type', 'like', "%{$search}%");
            });
        }

        if ($request->has('filter.is_active') && $request->input('filter.is_active') !== '') {
            $query->where('is_active', filter_var($request->input('filter.is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $sortCol = $this->queryService->safeSort($request, self::ALLOWED_SORT_COLUMNS, 'sort_order');
        $sortDir = $this->queryService->sortDirection($request, 'asc');
        $query->orderBy($sortCol, $sortDir)->orderBy('name');

        $p = $query->paginate($this->queryService->cappedPerPage($request, 100));

        return response()->json([
            'data' => WorkwearFabricResource::collection($p->items()),
            'total' => $p->total(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('viewAny', WorkwearModel::class);

        $fabric = WorkwearFabric::withCount('workwearModels')->findOrFail($id);

        return response()->json([
            'data' => new WorkwearFabricResource($fabric),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WorkwearModel::class);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('workwear_fabrics', 'code')->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:150',
            'composition' => 'nullable|string|max:255',
            'weight_gsm' => 'nullable|integer|min:10|max:2500',
            'weave_type' => 'nullable|string|max:100',
            'properties' => 'nullable|array|max:30',
            'properties.*' => 'required|string|max:100',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:65535',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? ((int) WorkwearFabric::max('sort_order') + 10);

        $fabric = WorkwearFabric::create($validated);
        $fabric->loadCount('workwearModels');

        return response()->json([
            'message' => 'Workwear fabric created.',
            'data' => new WorkwearFabricResource($fabric),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize('update', WorkwearModel::class);

        $fabric = WorkwearFabric::findOrFail($id);

        $validated = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('workwear_fabrics', 'code')->ignore($fabric->id)->whereNull('deleted_at'),
            ],
            'name' => 'sometimes|required|string|max:150',
            'composition' => 'nullable|string|max:255',
            'weight_gsm' => 'nullable|integer|min:10|max:2500',
            'weave_type' => 'nullable|string|max:100',
            'properties' => 'nullable|array|max:30',
            'properties.*' => 'required|string|max:100',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:65535',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $fabric->update($validated);

        // Keep denormalized `fabric` label in sync on linked workwear models
        if (isset($validated['name'])) {
            WorkwearModel::where('fabric_id', $fabric->id)->update(['fabric' => $fabric->name]);
        }

        $fabric->loadCount('workwearModels');

        return response()->json([
            'message' => 'Workwear fabric updated.',
            'data' => new WorkwearFabricResource($fabric),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('delete', WorkwearModel::class);

        $fabric = WorkwearFabric::withCount('workwearModels')->findOrFail($id);

        if ($fabric->workwear_models_count > 0) {
            return response()->json([
                'message' => 'Cannot delete fabric: it is currently assigned to one or more workwear models.',
            ], 422);
        }

        $fabric->delete();

        return response()->json([
            'message' => 'Workwear fabric deleted.',
        ]);
    }

    public function seedDefaults(): JsonResponse
    {
        $this->authorize('create', WorkwearModel::class);

        (new WorkwearFabricSeeder)->run();

        $fabrics = WorkwearFabric::withCount('workwearModels')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Default technical workwear fabrics seeded.',
            'data' => WorkwearFabricResource::collection($fabrics),
        ]);
    }
}
