<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialAccountTransaction;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Finance\Models\SalesInvoice;
use App\Modules\Finance\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly FinancialPeriodService $periodService,
        private readonly SequenceService $sequenceService,
        private readonly FinancialTransactionService $txService,
    ) {}

    /**
     * Create and confirm a payment in a single transaction.
     */
    public function create(array $data, string $createdBy): Payment
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $date   = \Carbon\Carbon::parse($data['payment_date']);
            $period = $this->periodService->assertPeriodOpen($date);

            $payment = Payment::create([
                'payment_number'      => $this->sequenceService->next('PAY'),
                'direction'           => $data['direction'],
                'type'                => $data['type'] ?? 'standard',
                'payment_date'        => $date->toDateString(),
                'financial_period_id' => $period->id,
                'financial_account_id' => $data['financial_account_id'],
                'party_type'          => $data['party_type'] ?? null,
                'party_id'            => $data['party_id'] ?? null,
                'party_name'          => $data['party_name'] ?? null,
                'method'              => $data['method'] ?? null,
                'reference'           => $data['reference'] ?? null,
                'currency'            => $data['currency'] ?? 'TND',
                'amount'              => $data['amount'],
                'amount_allocated'    => 0,
                'amount_unallocated'  => $data['amount'],
                'status'              => 'draft',
                'cost_center_id'      => $data['cost_center_id'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'created_by'          => $createdBy,
            ]);

            // Auto-confirm if requested
            if ($data['confirm'] ?? false) {
                $this->confirm($payment, $createdBy);
            }

            return $payment->fresh(['financialAccount', 'period', 'allocations']);
        });
    }

    /**
     * Confirm a draft payment — posts it to the account ledger.
     */
    public function confirm(Payment $payment, string $confirmedBy): Payment
    {
        if ($payment->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft payments can be confirmed.'],
            ]);
        }

        return DB::transaction(function () use ($payment, $confirmedBy) {
            // Lock payment and account for update
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            $account = FinancialAccount::lockForUpdate()->findOrFail($payment->financial_account_id);

            $newBalance = $payment->direction === 'in'
                ? $account->current_balance + $payment->amount
                : $account->current_balance - $payment->amount;

            // Create account transaction
            FinancialAccountTransaction::create([
                'financial_account_id' => $account->id,
                'transaction_date'     => $payment->payment_date,
                'type'                 => $payment->direction === 'in' ? 'credit' : 'debit',
                'amount'               => $payment->amount,
                'balance_after'        => $newBalance,
                'reference'            => $payment->payment_number,
                'description'          => "Payment {$payment->payment_number}",
                'source_type'          => Payment::class,
                'source_id'            => $payment->id,
                'created_by'           => $confirmedBy,
            ]);

            // Update account balance
            $account->current_balance = $newBalance;
            $account->save();

            // Update payment status
            $payment->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => $confirmedBy,
            ]);

            // Post to financial transaction log
            $this->txService->record([
                'date'        => $payment->payment_date,
                'type'        => $payment->direction === 'in' ? 'customer_payment' : 'supplier_payment',
                'direction'   => $payment->direction,
                'amount'      => $payment->amount,
                'source_type' => Payment::class,
                'source_id'   => $payment->id,
                'party_type'  => $payment->party_type,
                'party_id'    => $payment->party_id,
                'party_name'  => $payment->party_name,
                'reference'   => $payment->payment_number,
                'description' => "Payment {$payment->payment_number}",
                'period_id'   => $payment->financial_period_id,
            ], $confirmedBy);

            return $payment->fresh();
        });
    }

    /**
     * Allocate a payment to one or more invoices.
     */
    public function allocate(Payment $payment, array $allocations, string $createdBy): Payment
    {
        return DB::transaction(function () use ($payment, $allocations, $createdBy) {
            if ($payment->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'payment' => ['Only confirmed payments can be allocated.'],
                ]);
            }

            $totalToAllocate = array_sum(array_column($allocations, 'amount'));

            if ($totalToAllocate > $payment->amount_unallocated) {
                throw ValidationException::withMessages([
                    'amount' => [
                        "Total allocation ({$totalToAllocate}) exceeds unallocated balance ({$payment->amount_unallocated}).",
                    ],
                ]);
            }

            foreach ($allocations as $alloc) {
                PaymentAllocation::create([
                    'payment_id'       => $payment->id,
                    'invoice_type'     => $alloc['invoice_type'],
                    'invoice_id'       => $alloc['invoice_id'],
                    'allocated_amount' => $alloc['amount'],
                    'allocated_date'   => now()->toDateString(),
                    'notes'            => $alloc['notes'] ?? null,
                    'created_by'       => $createdBy,
                ]);

                // Recalculate invoice balance
                if ($alloc['invoice_type'] === 'sales_invoice') {
                    $invoice = SalesInvoice::findOrFail($alloc['invoice_id']);
                    $invoice->recalculateBalance();
                } elseif ($alloc['invoice_type'] === 'supplier_invoice') {
                    $invoice = SupplierInvoice::findOrFail($alloc['invoice_id']);
                    $invoice->recalculateBalance();
                }
            }

            $payment->refreshAllocationTotals();

            return $payment->fresh(['allocations']);
        });
    }
}
