<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\SalesInvoice;
use App\Modules\Finance\Models\SupplierInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly SupplierInvoiceService $supplierInvoiceService,
    ) {}

    public function getKpis(): array
    {
        // 1. Cash and Bank Balances
        $cashAccounts = FinancialAccount::where('is_active', true)->whereIn('type', ['cash', 'petty_cash'])->get();
        $bankAccounts = FinancialAccount::where('is_active', true)->where('type', 'bank')->get();

        $cashBalance = (float) $cashAccounts->sum('current_balance');
        $bankBalance = (float) $bankAccounts->sum('current_balance');
        $totalLiquidity = $cashBalance + $bankBalance;

        // 2. Receivables (AR)
        $arQuery = SalesInvoice::posted()->where('payment_status', '!=', 'paid')->where('type', 'invoice');
        $totalAr = (float) $arQuery->sum('amount_outstanding');
        $overdueAr = (float) (clone $arQuery)->overdue()->sum('amount_outstanding');

        // 3. Payables (AP)
        $apQuery = SupplierInvoice::where('status', 'approved')->where('payment_status', '!=', 'paid');
        $totalAp = (float) $apQuery->sum('amount_outstanding');
        $overdueAp = (float) (clone $apQuery)->overdue()->sum('amount_outstanding');

        // 4. Period / YTD Sales, Purchases, Expenses
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $startOfYear = Carbon::now()->startOfYear()->toDateString();

        $monthlySales = (float) SalesInvoice::posted()
            ->where('invoice_date', '>=', $startOfMonth)
            ->where('type', 'invoice')
            ->sum('subtotal_ht');

        $monthlyPurchases = (float) SupplierInvoice::where('status', 'approved')
            ->where('invoice_date', '>=', $startOfMonth)
            ->sum('subtotal_ht');

        $monthlyExpenses = (float) Expense::posted()
            ->where('expense_date', '>=', $startOfMonth)
            ->sum('amount_ht');

        $monthlyGrossMargin = $monthlySales - ($monthlyPurchases + $monthlyExpenses);

        // 5. Cash Flow (Money In / Money Out this month)
        $moneyInMonth = (float) Payment::where('status', 'confirmed')
            ->where('direction', 'in')
            ->where('payment_date', '>=', $startOfMonth)
            ->sum('amount');

        $moneyOutPayments = (float) Payment::where('status', 'confirmed')
            ->where('direction', 'out')
            ->where('payment_date', '>=', $startOfMonth)
            ->sum('amount');

        $moneyOutDirectExpenses = (float) Expense::posted()
            ->whereNull('related_doc_type')
            ->where('expense_date', '>=', $startOfMonth)
            ->sum('amount_ttc');

        $moneyOutMonth = $moneyOutPayments + $moneyOutDirectExpenses;
        $netCashMovement = $moneyInMonth - $moneyOutMonth;

        // 6. Aging breakdowns
        $arAging = $this->salesInvoiceService->calculateAging();
        $apAging = $this->supplierInvoiceService->calculateAging();

        // 7. Recent 5 financial activities
        $recentPayments = Payment::with(['financialAccount'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return [
            'balances' => [
                'cash'            => round($cashBalance, 3),
                'bank'            => round($bankBalance, 3),
                'total_liquidity' => round($totalLiquidity, 3),
            ],
            'receivables' => [
                'total'   => round($totalAr, 3),
                'overdue' => round($overdueAr, 3),
                'current' => round(max(0, $totalAr - $overdueAr), 3),
                'aging'   => $arAging,
            ],
            'payables' => [
                'total'   => round($totalAp, 3),
                'overdue' => round($overdueAp, 3),
                'current' => round(max(0, $totalAp - $overdueAp), 3),
                'aging'   => $apAging,
            ],
            'performance' => [
                'sales_month'        => round($monthlySales, 3),
                'purchases_month'    => round($monthlyPurchases, 3),
                'expenses_month'     => round($monthlyExpenses, 3),
                'gross_margin_month' => round($monthlyGrossMargin, 3),
            ],
            'cash_flow' => [
                'opening_estimate'  => round($totalLiquidity - $netCashMovement, 3),
                'money_in'          => round($moneyInMonth, 3),
                'money_out'         => round($moneyOutMonth, 3),
                'net_movement'      => round($netCashMovement, 3),
                'closing_balance'   => round($totalLiquidity, 3),
            ],
            'recent_payments' => $recentPayments,
        ];
    }
}
