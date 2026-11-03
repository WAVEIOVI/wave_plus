<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function __construct(
        private readonly AccountingService $accountingService,
    ) {}

    public function chartOfAccounts(Request $request): JsonResponse
    {
        $coa = $this->accountingService->getChartOfAccounts();

        return response()->json([
            'success' => true,
            'data' => $coa,
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $accounts = $this->accountingService->listAccounts();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:chart_of_accounts,code',
            'name' => 'required|string|max:150',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense',
            'parent_id' => 'nullable|ulid|exists:chart_of_accounts,id',
            'is_reconciliation' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        $account = $this->accountingService->createAccount($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully in Chart of Accounts',
            'data' => $account,
        ], 201);
    }

    public function journalEntries(Request $request): JsonResponse
    {
        $entries = $this->accountingService->listJournalEntries($request->all());

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }

    public function storeJournalEntry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entry_date' => 'required|date',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:draft,posted',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.description' => 'nullable|string',
            'lines.*.cost_center_id' => 'nullable|exists:cost_centers,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
        ]);

        $entry = $this->accountingService->createJournalEntry($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Journal entry recorded successfully',
            'data' => $entry,
        ], 201);
    }
}
