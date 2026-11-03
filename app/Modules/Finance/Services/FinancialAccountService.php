<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialAccountTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FinancialAccountService
{
    public function list(array $filters = []): Collection|LengthAwarePaginator
    {
        $query = FinancialAccount::with('creator')->orderBy('type')->orderBy('name');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['paginate'])) {
            return $query->paginate((int) ($filters['per_page'] ?? 15));
        }

        return $query->get();
    }

    public function find(string $id): FinancialAccount
    {
        return FinancialAccount::with(['creator', 'transactions' => function ($q) {
            $q->orderByDesc('transaction_date')->orderByDesc('id')->take(50);
        }])->findOrFail($id);
    }

    public function create(array $data, string $createdBy): FinancialAccount
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $openingBalance = (float) ($data['opening_balance'] ?? 0);

            $account = FinancialAccount::create([
                'name' => $data['name'],
                'type' => $data['type'], // cash | bank | petty_cash | other
                'currency' => $data['currency'] ?? 'TND',
                'account_number' => $data['account_number'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'iban' => $data['iban'] ?? null,
                'swift_bic' => $data['swift_bic'] ?? null,
                'opening_balance' => $openingBalance,
                'current_balance' => $openingBalance,
                'is_active' => $data['is_active'] ?? true,
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
            ]);

            if ($openingBalance > 0) {
                FinancialAccountTransaction::create([
                    'financial_account_id' => $account->id,
                    'transaction_date' => now()->toDateString(),
                    'type' => 'credit',
                    'amount' => $openingBalance,
                    'balance_after' => $openingBalance,
                    'reference' => 'OPENING',
                    'description' => 'Opening balance',
                    'created_by' => $createdBy,
                ]);
            }

            return $account;
        });
    }

    public function update(FinancialAccount $account, array $data, string $updatedBy): FinancialAccount
    {
        $account->update(array_merge($data, [
            'updated_by' => $updatedBy,
        ]));

        return $account->fresh();
    }

    public function getTransactions(string $accountId, array $filters = []): LengthAwarePaginator
    {
        $query = FinancialAccountTransaction::where('financial_account_id', $accountId)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        if (! empty($filters['from_date'])) {
            $query->where('transaction_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('transaction_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 25));
    }

    public function reconcile(FinancialAccount $account, array $transactionIds, string $reconciledBy): array
    {
        FinancialAccountTransaction::where('financial_account_id', $account->id)
            ->whereIn('id', $transactionIds)
            ->update([
                'is_reconciled' => true,
                'reconciled_at' => now(),
            ]);

        $account->update([
            'last_reconciled_at' => now(),
            'updated_by' => $reconciledBy,
        ]);

        return [
            'reconciled_count' => count($transactionIds),
            'account' => $account->fresh(),
        ];
    }
}
