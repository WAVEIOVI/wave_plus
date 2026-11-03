<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $expenses = $this->expenseService->list($request->all());

        return response()->json([
            'success' => true,
            'data' => $expenses,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $expense = $this->expenseService->find($id);

        return response()->json([
            'success' => true,
            'data' => $expense,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expense_date' => 'required|date',
            'category' => 'required|string|max:100',
            'currency' => 'nullable|string|max:3',
            'amount_ht' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'amount_ttc' => 'nullable|numeric|min:0',
            'tax_rate_id' => 'nullable|ulid|exists:tax_rates,id',
            'financial_account_id' => 'nullable|ulid|exists:financial_accounts,id',
            'cost_center_id' => 'nullable|ulid|exists:cost_centers,id',
            'provider_type' => 'nullable|string|max:50',
            'provider_id' => 'nullable|ulid',
            'provider_name' => 'nullable|string|max:150',
            'vehicle_id' => 'nullable|ulid',
            'sales_order_id' => 'nullable|ulid',
            'mission_reference' => 'nullable|string',
            'dwo_reference' => 'nullable|string',
            'related_doc_type' => 'nullable|string',
            'related_doc_id' => 'nullable|ulid',
            'status' => 'nullable|string|in:draft,posted',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'receipt_path' => 'nullable|string',
        ]);

        $expense = $this->expenseService->create($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Expense recorded successfully',
            'data' => $expense,
        ], 201);
    }

    public function postExpense(Expense $expense, Request $request): JsonResponse
    {
        $posted = $this->expenseService->postExpense($expense, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Expense posted successfully',
            'data' => $posted,
        ]);
    }

    public function cancel(Expense $expense, Request $request): JsonResponse
    {
        $cancelled = $this->expenseService->cancel($expense, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Expense cancelled successfully',
            'data' => $cancelled,
        ]);
    }
}
