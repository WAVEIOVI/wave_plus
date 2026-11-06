<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialAccountTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        private readonly FinancialPeriodService $periodService,
        private readonly SequenceService $sequenceService,
        private readonly FinancialTransactionService $txService,
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Expense::with(['financialAccount', 'costCenter', 'period'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('provider_name', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['cost_center_id'])) {
            $query->where('cost_center_id', $filters['cost_center_id']);
        }

        if (!empty($filters['vehicle_id'])) {
            $query->where('vehicle_id', $filters['vehicle_id']);
        }

        if (!empty($filters['sales_order_id'])) {
            $query->where('sales_order_id', $filters['sales_order_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('expense_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('expense_date', '<=', $filters['to_date']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function find(string $id): Expense
    {
        return Expense::with(['financialAccount', 'costCenter', 'period', 'taxRate'])
            ->findOrFail($id);
    }

    public function create(array $data, string $createdBy): Expense
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $date = Carbon::parse($data['expense_date'] ?? now());
            $period = $this->periodService->assertPeriodOpen($date);

            $amountHt = (float) ($data['amount_ht'] ?? 0);
            $taxAmount = (float) ($data['tax_amount'] ?? 0);
            $amountTtc = !empty($data['amount_ttc']) ? (float) $data['amount_ttc'] : ($amountHt + $taxAmount);

            $expenseNumber = $this->sequenceService->next('EXP');

            $expense = Expense::create([
                'expense_number'       => $expenseNumber,
                'expense_date'         => $date->toDateString(),
                'financial_period_id'  => $period->id,
                'category'             => $data['category'],
                'currency'             => $data['currency'] ?? 'TND',
                'amount_ht'            => $amountHt,
                'tax_amount'           => $taxAmount,
                'amount_ttc'           => $amountTtc,
                'tax_rate_id'          => $data['tax_rate_id'] ?? null,
                'financial_account_id' => $data['financial_account_id'] ?? null,
                'cost_center_id'       => $data['cost_center_id'] ?? null,
                'provider_type'        => $data['provider_type'] ?? null,
                'provider_id'          => $data['provider_id'] ?? null,
                'provider_name'        => $data['provider_name'] ?? null,
                'vehicle_id'           => $data['vehicle_id'] ?? null,
                'sales_order_id'       => $data['sales_order_id'] ?? null,
                'mission_reference'    => $data['mission_reference'] ?? null,
                'dwo_reference'        => $data['dwo_reference'] ?? null,
                'related_doc_type'     => $data['related_doc_type'] ?? null,
                'related_doc_id'       => $data['related_doc_id'] ?? null,
                'status'               => 'draft',
                'description'          => $data['description'] ?? null,
                'notes'                => $data['notes'] ?? null,
                'receipt_path'         => $data['receipt_path'] ?? null,
                'created_by'           => $createdBy,
                'updated_by'           => $createdBy,
            ]);

            if (($data['status'] ?? null) === 'posted') {
                $expense = $this->postExpense($expense, $createdBy);
            }

            return $expense->fresh(['financialAccount', 'costCenter', 'period']);
        });
    }

    public function postExpense(Expense $expense, string $postedBy): Expense
    {
        if ($expense->status === 'posted') {
            return $expense;
        }

        $date = Carbon::parse($expense->expense_date);
        $period = $this->periodService->assertPeriodOpen($date);

        return DB::transaction(function () use ($expense, $postedBy, $period) {
            // If linked to financial account, deduct balance
            if ($expense->financial_account_id) {
                $account = FinancialAccount::findOrFail($expense->financial_account_id);
                $newBalance = $account->current_balance - $expense->amount_ttc;

                FinancialAccountTransaction::create([
                    'financial_account_id' => $account->id,
                    'transaction_date'     => $expense->expense_date,
                    'type'                 => 'debit',
                    'amount'               => $expense->amount_ttc,
                    'balance_after'        => $newBalance,
                    'reference'            => $expense->expense_number,
                    'description'          => "Expense: {$expense->category} - {$expense->expense_number}",
                    'source_type'          => Expense::class,
                    'source_id'            => $expense->id,
                    'created_by'           => $postedBy,
                ]);

                $account->current_balance = $newBalance;
                $account->save();
            }

            $expense->update([
                'status'              => 'posted',
                'posted_at'           => now(),
                'posted_by'           => $postedBy,
                'financial_period_id' => $period->id,
                'updated_by'          => $postedBy,
            ]);

            // Central financial transaction record
            $this->txService->record([
                'date'           => $expense->expense_date,
                'type'           => 'expense',
                'direction'      => 'out',
                'amount'         => $expense->amount_ttc,
                'source_type'    => Expense::class,
                'source_id'      => $expense->id,
                'party_type'     => $expense->provider_type,
                'party_id'       => $expense->provider_id,
                'party_name'     => $expense->provider_name,
                'cost_center_id' => $expense->cost_center_id,
                'reference'      => $expense->expense_number,
                'description'    => "Expense {$expense->category}: {$expense->description}",
                'period_id'      => $period->id,
            ], $postedBy);

            return $expense->fresh();
        });
    }

    public function cancel(Expense $expense, string $cancelledBy): Expense
    {
        if ($expense->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => ['Expense is already cancelled.'],
            ]);
        }

        return DB::transaction(function () use ($expense, $cancelledBy) {
            // Lock expense for update
            $expense = Expense::lockForUpdate()->findOrFail($expense->id);

            if ($expense->status === 'posted' && $expense->financial_account_id) {
                // Lock account and refund
                $account = FinancialAccount::lockForUpdate()->findOrFail($expense->financial_account_id);
                $newBalance = $account->current_balance + $expense->amount_ttc;

                FinancialAccountTransaction::create([
                    'financial_account_id' => $account->id,
                    'transaction_date'     => now()->toDateString(),
                    'type'                 => 'credit',
                    'amount'               => $expense->amount_ttc,
                    'balance_after'        => $newBalance,
                    'reference'            => "CANC-{$expense->expense_number}",
                    'description'          => "Cancellation of Expense {$expense->expense_number}",
                    'source_type'          => Expense::class,
                    'source_id'            => $expense->id,
                    'created_by'           => $cancelledBy,
                ]);

                $account->current_balance = $newBalance;
                $account->save();
            }

            $expense->update([
                'status'     => 'cancelled',
                'updated_by' => $cancelledBy,
            ]);

            return $expense->fresh();
        });
    }
}
