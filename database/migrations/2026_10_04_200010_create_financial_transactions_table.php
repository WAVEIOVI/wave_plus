<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('transaction_number', 40)->unique();
            $table->date('transaction_date');
            $table->ulid('financial_period_id')->nullable();
            $table->string('type', 50);
            // Types: sales_invoice_posted | supplier_invoice_posted |
            //        customer_payment | supplier_payment | expense_posted |
            //        vehicle_expense | mission_cost | credit_note | debit_note |
            //        stock_adjustment | other
            $table->string('direction', 10); // in | out | neutral
            $table->string('currency', 3)->default('TND');
            $table->decimal('amount', 16, 3);

            // Polymorphic source
            $table->string('source_type', 80);
            $table->ulid('source_id');

            // Party (client / supplier / etc.)
            $table->string('party_type', 50)->nullable();
            $table->string('party_id', 26)->nullable();
            $table->string('party_name', 150)->nullable();

            $table->ulid('cost_center_id')->nullable();
            $table->string('reference', 150)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('transaction_date');
            $table->index('financial_period_id');
            $table->index('type');
            $table->index(['source_type', 'source_id']);
            $table->index(['party_type', 'party_id']);
            $table->index('cost_center_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
