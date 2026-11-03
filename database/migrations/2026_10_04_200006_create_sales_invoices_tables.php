<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('invoice_number', 40)->unique();
            $table->string('type', 20)->default('invoice'); // invoice | credit_note | debit_note
            $table->foreignUlid('client_id')->constrained('clients')->restrictOnDelete();
            $table->ulid('financial_period_id')->nullable();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            // Originating document (polymorphic-style)
            $table->string('source_type', 50)->nullable(); // sales_order | delivery_note | service_work_order | contract | manual
            $table->ulid('source_id')->nullable();
            $table->string('source_reference', 100)->nullable(); // SO number, DN number etc.

            // Related to (for credit/debit notes referencing original invoice)
            $table->ulid('related_invoice_id')->nullable();

            // Amounts
            $table->string('currency', 3)->default('TND');
            $table->decimal('subtotal_ht', 16, 3)->default(0);
            $table->decimal('tax_amount', 16, 3)->default(0);
            $table->decimal('total_ttc', 16, 3)->default(0);
            $table->decimal('amount_paid', 16, 3)->default(0);
            $table->decimal('amount_outstanding', 16, 3)->default(0);

            // Status
            $table->string('status', 30)->default('draft'); // draft | posted | partial | paid | overdue | cancelled | void
            $table->string('payment_status', 30)->default('unpaid'); // unpaid | partial | paid | void
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignUlid('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();

            // Payment terms
            $table->string('payment_terms', 50)->nullable();

            // Cost center
            $table->ulid('cost_center_id')->nullable();

            $table->text('internal_notes')->nullable();
            $table->text('client_notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('financial_period_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('due_date');
            $table->index(['source_type', 'source_id']);
            $table->index('type');
        });

        Schema::create('sales_invoice_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('sales_invoice_id');
            $table->unsignedSmallInteger('line_number')->default(1);
            $table->string('description', 255);
            $table->ulid('catalog_item_id')->nullable();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('unit_price_ht', 16, 3)->default(0);
            $table->ulid('tax_rate_id')->nullable();
            $table->decimal('tax_rate_pct', 8, 4)->default(0); // snapshot of rate at invoice time
            $table->decimal('line_ht', 16, 3)->default(0);
            $table->decimal('line_tax', 16, 3)->default(0);
            $table->decimal('line_ttc', 16, 3)->default(0);
            $table->ulid('cost_center_id')->nullable();
            $table->string('source_line_id', 26)->nullable(); // SO line, DN line ref
            $table->timestamps();

            $table->index('sales_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_lines');
        Schema::dropIfExists('sales_invoices');
    }
};
