<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Finance\Models\CostCenter;
use App\Modules\Finance\Models\FinancialAccount;
use App\Modules\Finance\Models\FinancialPeriod;
use App\Modules\Finance\Services\FinancialPeriodService;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Services\ProfitabilityService;
use App\Modules\Finance\Services\SalesInvoiceService;
use App\Modules\Finance\Services\SupplierInvoiceService;
use App\Modules\Finance\Services\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialCenterFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create or get testing user
        $this->user = User::first() ?? User::factory()->create([
            'email' => 'finance_test@waveplus.local',
        ]);
    }

    public function test_customer_invoice_and_payment_flow(): void
    {
        $client = Client::first() ?? Client::create([
            'company_name' => 'Test Client Corp',
            'legal_name'   => 'Test Client SARL',
            'client_type'  => 'enterprise',
            'credit_limit' => 50000,
        ]);

        $invoiceService = app(SalesInvoiceService::class);
        $paymentService = app(PaymentService::class);

        // 1. Create Sales Invoice
        $invoice = $invoiceService->create([
            'client_id'    => $client->id,
            'type'         => 'invoice',
            'invoice_date' => now()->toDateString(),
            'due_date'     => now()->addDays(30)->toDateString(),
            'lines'        => [
                [
                    'description' => 'Field Maintenance Service',
                    'quantity'    => 2,
                    'unit_price'  => 1000,
                    'tax_rate'    => 19,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals(2000, (float) $invoice->subtotal_ht);
        $this->assertEquals(380, (float) $invoice->tax_amount);
        $this->assertEquals(2380, (float) $invoice->total_ttc);
        $this->assertEquals('draft', $invoice->status);

        // 2. Issue Invoice
        $issued = $invoiceService->issue($invoice, $this->user->id);
        $this->assertEquals('issued', $issued->status);
        $this->assertEquals(2380, (float) $issued->amount_outstanding);

        // 3. Create Bank Account and Record Payment
        $account = FinancialAccount::firstOrCreate(
            ['name' => 'Testing Bank'],
            ['type' => 'bank', 'current_balance' => 10000, 'is_active' => true]
        );

        $payment = $paymentService->create([
            'direction'            => 'in',
            'payment_date'         => now()->toDateString(),
            'financial_account_id' => $account->id,
            'party_type'           => 'client',
            'party_id'             => (string) $client->id,
            'party_name'           => $client->company_name,
            'method'               => 'bank_transfer',
            'amount'               => 1500,
            'confirm'              => true,
        ], $this->user->id);

        $this->assertEquals('confirmed', $payment->status);

        // 4. Allocate payment to invoice
        $allocatedPayment = $paymentService->allocate($payment, [
            [
                'invoice_type' => 'sales_invoice',
                'invoice_id'   => (string) $issued->id,
                'amount'       => 1500,
            ],
        ], $this->user->id);

        $this->assertEquals(1500, (float) $allocatedPayment->amount_allocated);

        // 5. Verify invoice outstanding balance updated
        $freshInvoice = $issued->fresh();
        $this->assertEquals(1500, (float) $freshInvoice->amount_paid);
        $this->assertEquals(880, (float) $freshInvoice->amount_outstanding);
        $this->assertEquals('partial', $freshInvoice->payment_status);
    }

    public function test_supplier_invoice_and_matching_flow(): void
    {
        $supplier = Supplier::first() ?? Supplier::create([
            'company_name' => 'Global Parts SARL',
            'legal_name'   => 'Global Parts SARL',
            'supplier_type'=> 'goods',
        ]);

        $supplierInvoiceService = app(SupplierInvoiceService::class);

        $sinv = $supplierInvoiceService->create([
            'supplier_id'          => $supplier->id,
            'supplier_invoice_ref' => 'BILL-2026-001',
            'purchase_order_id'    => 'PO-2026-009',
            'invoice_date'         => now()->toDateString(),
            'due_date'             => now()->addDays(45)->toDateString(),
            'lines'                => [
                [
                    'description' => 'Brake pads & filters',
                    'quantity'    => 5,
                    'unit_price'  => 200,
                    'tax_rate'    => 19,
                ],
            ],
        ], $this->user->id);

        $this->assertEquals('matched', $sinv->matching_status);
        $this->assertEquals(1000, (float) $sinv->subtotal_ht);
        $this->assertEquals(1190, (float) $sinv->total_ttc);

        $approved = $supplierInvoiceService->approve($sinv, $this->user->id);
        $this->assertEquals('approved', $approved->status);
    }

    public function test_operational_expense_and_cost_center_flow(): void
    {
        $costCenter = CostCenter::firstOrCreate(
            ['code' => 'TEST-CC'],
            ['name' => 'Testing Operations', 'is_active' => true]
        );

        $account = FinancialAccount::firstOrCreate(
            ['name' => 'Petty Cash Desk'],
            ['type' => 'petty_cash', 'current_balance' => 5000, 'is_active' => true]
        );

        $balanceBefore = (float) $account->current_balance;

        $expenseService = app(ExpenseService::class);

        $expense = $expenseService->create([
            'category'             => 'Fuel',
            'expense_date'         => now()->toDateString(),
            'amount_ht'            => 150,
            'tax_amount'           => 28.5,
            'amount_ttc'           => 178.5,
            'financial_account_id' => $account->id,
            'cost_center_id'       => $costCenter->id,
            'vehicle_id'           => 'VEH-001',
            'description'          => 'Field mission diesel top-up',
            'status'               => 'posted',
        ], $this->user->id);

        $this->assertEquals('posted', $expense->status);

        // Account balance was deducted
        $this->assertEquals($balanceBefore - 178.5, (float) $account->fresh()->current_balance);
    }

    public function test_closed_period_prevents_uncontrolled_modification(): void
    {
        $periodService = app(FinancialPeriodService::class);
        $period = $periodService->currentPeriod();

        // Close period
        $periodService->closePeriod($period, $this->user->id);

        // Assert assertPeriodOpen throws ValidationException
        $this->expectException(ValidationException::class);
        $periodService->assertPeriodOpen(now());

        // Cleanup: reopen period
        $periodService->reopenPeriod($period);
    }
}
