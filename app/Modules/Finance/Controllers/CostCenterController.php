<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\CostCenter;
use App\Modules\Finance\Services\CostCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CostCenterController extends Controller
{
    public function __construct(
        private readonly CostCenterService $costCenterService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CostCenter::class);

        $costCenters = $this->costCenterService->list($request->all());

        return response()->json([
            'success' => true,
            'data' => $costCenters,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CostCenter::class);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:cost_centers,code',
            'name' => 'required|string|max:100',
            'parent_id' => 'nullable|ulid|exists:cost_centers,id',
            'manager_id' => 'nullable|ulid|exists:users,id',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $costCenter = $this->costCenterService->create($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Cost center created successfully',
            'data' => $costCenter,
        ], 201);
    }

    public function update(CostCenter $costCenter, Request $request): JsonResponse
    {
        $this->authorize('update', $costCenter);

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'parent_id' => 'nullable|ulid|exists:cost_centers,id',
            'manager_id' => 'nullable|ulid|exists:users,id',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $updated = $this->costCenterService->update($costCenter, $validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Cost center updated successfully',
            'data' => $updated,
        ]);
    }

    public function performance(string $id): JsonResponse
    {
        $this->authorize('viewAny', CostCenter::class);

        $perf = $this->costCenterService->getPerformance($id);

        return response()->json([
            'success' => true,
            'data' => $perf,
        ]);
    }
}
