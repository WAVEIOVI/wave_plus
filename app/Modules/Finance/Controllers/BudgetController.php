<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Budget;
use App\Modules\Finance\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetService $budgetService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Budget::class);

        $budgets = $this->budgetService->list($request->all());

        return response()->json([
            'success' => true,
            'data' => $budgets,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $budget = $this->budgetService->find($id);
        $this->authorize('view', $budget);

        return response()->json([
            'success' => true,
            'data' => $budget,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Budget::class);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'nullable|string|in:annual,quarterly,monthly,project',
            'year' => 'required|integer',
            'month' => 'nullable|integer|min:1|max:12',
            'quarter' => 'nullable|integer|min:1|max:4',
            'cost_center_id' => 'nullable|ulid|exists:cost_centers,id',
            'status' => 'nullable|string|in:draft,approved,active,closed',
            'notes' => 'nullable|string',
            'lines' => 'nullable|array',
            'lines.*.category' => 'required|string',
            'lines.*.description' => 'nullable|string',
            'lines.*.budgeted_amount' => 'required|numeric|min:0',
        ]);

        $budget = $this->budgetService->create($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Budget created successfully',
            'data' => $budget,
        ], 201);
    }

    public function recalculate(Budget $budget, Request $request): JsonResponse
    {
        $this->authorize('update', $budget);

        $updated = $this->budgetService->recalculateActuals($budget);

        return response()->json([
            'success' => true,
            'message' => 'Budget actuals recalculated successfully from posted expenses',
            'data' => $updated,
        ]);
    }
}
