<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Services\FinancialAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialAccountController extends Controller
{
    public function __construct(
        private readonly FinancialAccountService $accountService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinancialAccount::class);

        $accounts = $this->accountService->list($request->all());

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $account = $this->accountService->find($id);
        $this->authorize('view', $account);

        return response()->json([
            'success' => true,
            'data' => $account,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', FinancialAccount::class);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:cash,bank,petty_cash,other',
            'currency' => 'nullable|string|max:3',
            'account_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:50',
            'swift_bic' => 'nullable|string|max:20',
            'opening_balance' => 'nullable|numeric',
            'cost_center_id' => 'nullable|ulid|exists:cost_centers,id',
            'notes' => 'nullable|string',
        ]);

        $account = $this->accountService->create($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Financial account created successfully',
            'data' => $account,
        ], 201);
    }

    public function update(FinancialAccount $financialAccount, Request $request): JsonResponse
    {
        $this->authorize('update', $financialAccount);

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'iban' => 'nullable|string|max:50',
            'swift_bic' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $account = $this->accountService->update($financialAccount, $validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Financial account updated successfully',
            'data' => $account,
        ]);
    }

    public function transactions(string $id, Request $request): JsonResponse
    {
        $this->authorize('viewAny', FinancialAccount::class);

        $transactions = $this->accountService->getTransactions($id, $request->all());

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    public function reconcile(FinancialAccount $financialAccount, Request $request): JsonResponse
    {
        $this->authorize('update', $financialAccount);

        $validated = $request->validate([
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'required|ulid',
        ]);

        $result = $this->accountService->reconcile($financialAccount, $validated['transaction_ids'], $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Transactions reconciled successfully',
            'data' => $result,
        ]);
    }
}
