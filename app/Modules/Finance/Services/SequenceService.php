<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use Illuminate\Support\Facades\DB;

class SequenceService
{
    /**
     * Generate the next formatted sequence number for a given prefix.
     * E.g.: INV-202610-0001
     */
    public function next(string $prefix): string
    {
        $yearMonth = now()->format('Ym');
        $lockKey = "seq_{$prefix}_{$yearMonth}";

        // Use atomic counter via DB or simple table query
        $count = match ($prefix) {
            'INV' => DB::table('sales_invoices')->where('invoice_number', 'like', "{$prefix}-{$yearMonth}-%")->count() + 1,
            'SINV' => DB::table('supplier_invoices')->where('invoice_number', 'like', "{$prefix}-{$yearMonth}-%")->count() + 1,
            'PAY' => DB::table('payments')->where('payment_number', 'like', "{$prefix}-{$yearMonth}-%")->count() + 1,
            'EXP' => DB::table('expenses')->where('expense_number', 'like', "{$prefix}-{$yearMonth}-%")->count() + 1,
            'FTX' => DB::table('financial_transactions')->where('transaction_number', 'like', "{$prefix}-{$yearMonth}-%")->count() + 1,
            'JE'  => DB::table('journal_entries')->where('entry_number', 'like', "{$prefix}-{$yearMonth}-%")->count() + 1,
            'BDG' => DB::table('budgets')->where('id', 'like', "{$prefix}-{$yearMonth}-%")->count() + 1,
            default => DB::table('financial_transactions')->count() + 1,
        };

        $sequence = str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$yearMonth}-{$sequence}";
    }
}
