<?php

declare(strict_types=1);

use App\Modules\Finance\Controllers\AccountingController;
use App\Modules\Finance\Controllers\BudgetController;
use App\Modules\Finance\Controllers\CostCenterController;
use App\Modules\Finance\Controllers\CustomerFinancialController;
use App\Modules\Finance\Controllers\ExpenseController;
use App\Modules\Finance\Controllers\FinancialAccountController;
use App\Modules\Finance\Controllers\FinancialDashboardController;
use App\Modules\Finance\Controllers\FinancialPeriodController;
use App\Modules\Finance\Controllers\PaymentController;
use App\Modules\Finance\Controllers\ProfitabilityController;
use App\Modules\Finance\Controllers\SalesInvoiceController;
use App\Modules\Finance\Controllers\SupplierFinancialController;
use App\Modules\Finance\Controllers\SupplierInvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Financial Center API Routes
|--------------------------------------------------------------------------
|
| Prefixed with /api/finance and protected by auth:sanctum middleware.
|
*/

Route::prefix('finance')
    ->middleware(['auth:sanctum'])
    ->group(function () {

        // ── Dashboard ────────────────────────────────────────────────
        Route::get('dashboard', [FinancialDashboardController::class, 'index']);

        // ── Customers / Receivables (AR) ─────────────────────────────
        Route::get('customers', [CustomerFinancialController::class, 'index']);
        Route::get('customers/{id}', [CustomerFinancialController::class, 'show']);

        // ── Suppliers / Payables (AP) ────────────────────────────────
        Route::get('suppliers', [SupplierFinancialController::class, 'index']);
        Route::get('suppliers/{id}', [SupplierFinancialController::class, 'show']);

        // ── Sales Invoices ───────────────────────────────────────────
        Route::get('sales-invoices/aging', [SalesInvoiceController::class, 'aging']);
        Route::get('sales-invoices', [SalesInvoiceController::class, 'index']);
        Route::get('sales-invoices/{id}', [SalesInvoiceController::class, 'show']);
        Route::post('sales-invoices', [SalesInvoiceController::class, 'store']);
        Route::post('sales-invoices/{salesInvoice}/issue', [SalesInvoiceController::class, 'issue']);
        Route::post('sales-invoices/{salesInvoice}/void', [SalesInvoiceController::class, 'void']);

        // ── Supplier Invoices ────────────────────────────────────────
        Route::get('supplier-invoices/aging', [SupplierInvoiceController::class, 'aging']);
        Route::get('supplier-invoices', [SupplierInvoiceController::class, 'index']);
        Route::get('supplier-invoices/{id}', [SupplierInvoiceController::class, 'show']);
        Route::post('supplier-invoices', [SupplierInvoiceController::class, 'store']);
        Route::post('supplier-invoices/{supplierInvoice}/approve', [SupplierInvoiceController::class, 'approve']);

        // ── Payments & Allocations ───────────────────────────────────
        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('payments/{id}', [PaymentController::class, 'show']);
        Route::post('payments', [PaymentController::class, 'store']);
        Route::post('payments/{payment}/confirm', [PaymentController::class, 'confirm']);
        Route::post('payments/{payment}/allocate', [PaymentController::class, 'allocate']);

        // ── Cash & Bank Accounts ─────────────────────────────────────
        Route::get('financial-accounts', [FinancialAccountController::class, 'index']);
        Route::get('financial-accounts/{id}', [FinancialAccountController::class, 'show']);
        Route::post('financial-accounts', [FinancialAccountController::class, 'store']);
        Route::put('financial-accounts/{financialAccount}', [FinancialAccountController::class, 'update']);
        Route::get('financial-accounts/{id}/transactions', [FinancialAccountController::class, 'transactions']);
        Route::post('financial-accounts/{financialAccount}/reconcile', [FinancialAccountController::class, 'reconcile']);

        // ── Expenses ─────────────────────────────────────────────────
        Route::get('expenses', [ExpenseController::class, 'index']);
        Route::get('expenses/{id}', [ExpenseController::class, 'show']);
        Route::post('expenses', [ExpenseController::class, 'store']);
        Route::post('expenses/{expense}/post', [ExpenseController::class, 'postExpense']);
        Route::post('expenses/{expense}/cancel', [ExpenseController::class, 'cancel']);

        // ── Profitability ────────────────────────────────────────────
        Route::get('profitability/overview', [ProfitabilityController::class, 'overview']);
        Route::get('profitability/sales-order/{id}', [ProfitabilityController::class, 'salesOrder']);

        // ── Cost Centers ─────────────────────────────────────────────
        Route::get('cost-centers', [CostCenterController::class, 'index']);
        Route::post('cost-centers', [CostCenterController::class, 'store']);
        Route::put('cost-centers/{costCenter}', [CostCenterController::class, 'update']);
        Route::get('cost-centers/{id}/performance', [CostCenterController::class, 'performance']);

        // ── Budgets ──────────────────────────────────────────────────
        Route::get('budgets', [BudgetController::class, 'index']);
        Route::get('budgets/{id}', [BudgetController::class, 'show']);
        Route::post('budgets', [BudgetController::class, 'store']);
        Route::post('budgets/{budget}/recalculate', [BudgetController::class, 'recalculate']);

        // ── Accounting / GL Foundation ───────────────────────────────
        Route::get('accounting/chart-of-accounts', [AccountingController::class, 'chartOfAccounts']);
        Route::get('accounting/accounts', [AccountingController::class, 'accounts']);
        Route::post('accounting/accounts', [AccountingController::class, 'storeAccount']);
        Route::get('accounting/journal-entries', [AccountingController::class, 'journalEntries']);
        Route::post('accounting/journal-entries', [AccountingController::class, 'storeJournalEntry']);

        // ── Financial Periods ────────────────────────────────────────
        Route::get('financial-periods/current', [FinancialPeriodController::class, 'current']);
        Route::get('financial-periods', [FinancialPeriodController::class, 'index']);
        Route::post('financial-periods/{financialPeriod}/close', [FinancialPeriodController::class, 'close']);
        Route::post('financial-periods/{financialPeriod}/reopen', [FinancialPeriodController::class, 'reopen']);
    });
