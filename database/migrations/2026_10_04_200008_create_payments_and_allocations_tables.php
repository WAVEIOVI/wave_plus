<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('payment_number', 40)->unique();
            $table->string('direction', 10); // in | out
            $table->string('type', 40)->default('standard');
            // money_in types:  customer_payment | advance_deposit | other_income | refund_out
            // money_out types: supplier_payment | employee_reimbursement | operational_expense | advance | refund_in

            $table->date('payment_date');
            $table->ulid('financial_period_id')->nullable();
            $table->ulid('financial_account_id'); // cash/bank account

            // Payer/Payee (polymorphic)
            $table->string('party_type', 50)->nullable(); // client | supplier | employee | other
            $table->string('party_id', 26)->nullable();   // FK to client, supplier, hr_employee etc.
            $table->string('party_name', 150)->nullable(); // denormalized for convenience

            $table->string('method', 30)->nullable(); // cash | bank_transfer | cheque | credit_card | other
            $table->string('reference', 150)->nullable(); // cheque num, transfer ref etc.
            $table->string('currency', 3)->default('TND');
            $table->decimal('amount', 16, 3);
            $table->decimal('amount_allocated', 16, 3)->default(0);
            $table->decimal('amount_unallocated', 16, 3)->default(0);

            $table->string('status', 20)->default('draft'); // draft | confirmed | cancelled | reversed
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->ulid('cost_center_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('direction');
            $table->index('payment_date');
            $table->index('financial_period_id');
            $table->index('financial_account_id');
            $table->index(['party_type', 'party_id']);
            $table->index('status');
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('payment_id');
            // The invoice being allocated (polymorphic: sales_invoice | supplier_invoice)
            $table->string('invoice_type', 50); // sales_invoice | supplier_invoice
            $table->ulid('invoice_id');
            $table->decimal('allocated_amount', 16, 3);
            $table->date('allocated_date');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('payment_id');
            $table->index(['invoice_type', 'invoice_id']);
        });

        // Financial account transaction ledger
        Schema::create('financial_account_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('financial_account_id');
            $table->date('transaction_date');
            $table->string('type', 30); // debit | credit
            $table->decimal('amount', 16, 3);
            $table->decimal('balance_after', 16, 3);
            $table->string('reference', 150)->nullable();
            $table->string('description', 255)->nullable();
            // Source (polymorphic)
            $table->string('source_type', 80)->nullable();
            $table->ulid('source_id')->nullable();
            $table->string('reconciliation_status', 20)->default('unreconciled');
            $table->date('reconciled_date')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('financial_account_id');
            $table->index('transaction_date');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_account_transactions');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
