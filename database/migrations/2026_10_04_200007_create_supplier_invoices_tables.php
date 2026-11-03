<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('invoice_number', 40)->unique(); // internal reference
            $table->string('supplier_invoice_ref', 100)->nullable(); // supplier's own invoice number
            $table->string('type', 20)->default('invoice'); // invoice | credit_note | debit_note
            $table->foreignUlid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->ulid('financial_period_id')->nullable();
            $table->ulid('purchase_order_id')->nullable(); // reference to PO
            $table->ulid('grn_id')->nullable();            // reference to GRN
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('TND');
            $table->decimal('subtotal_ht', 16, 3)->default(0);
            $table->decimal('tax_amount', 16, 3)->default(0);
            $table->decimal('total_ttc', 16, 3)->default(0);
            $table->decimal('amount_paid', 16, 3)->default(0);
            $table->decimal('amount_outstanding', 16, 3)->default(0);
            $table->string('status', 30)->default('draft'); // draft | posted | partial | paid | overdue | disputed | cancelled
            $table->string('payment_status', 30)->default('unpaid');
            $table->string('matching_status', 30)->default('unmatched'); // unmatched | partial | matched
            $table->timestamp('posted_at')->nullable();
            $table->string('payment_terms', 50)->nullable();
            $table->ulid('cost_center_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('supplier_id');
            $table->index('financial_period_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('due_date');
        });

        Schema::create('supplier_invoice_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('supplier_invoice_id');
            $table->unsignedSmallInteger('line_number')->default(1);
            $table->string('description', 255);
            $table->ulid('catalog_item_id')->nullable();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('unit_price_ht', 16, 3)->default(0);
            $table->ulid('tax_rate_id')->nullable();
            $table->decimal('tax_rate_pct', 8, 4)->default(0);
            $table->decimal('line_ht', 16, 3)->default(0);
            $table->decimal('line_tax', 16, 3)->default(0);
            $table->decimal('line_ttc', 16, 3)->default(0);
            $table->ulid('cost_center_id')->nullable();
            $table->timestamps();

            $table->index('supplier_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_lines');
        Schema::dropIfExists('supplier_invoices');
    }
};
