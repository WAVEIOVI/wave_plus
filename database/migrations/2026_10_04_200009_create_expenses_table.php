<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('expense_number', 40)->unique();
            $table->date('expense_date');
            $table->ulid('financial_period_id')->nullable();
            $table->string('category', 50);
            // categories: employee | vehicle_fuel | vehicle_maintenance | vehicle_rental |
            //             logistics | workshop | operational_hub | administration |
            //             utilities | maintenance | subscription | subcontract | miscellaneous

            $table->string('currency', 3)->default('TND');
            $table->decimal('amount_ht', 16, 3)->default(0);
            $table->decimal('tax_amount', 16, 3)->default(0);
            $table->decimal('amount_ttc', 16, 3)->default(0);
            $table->ulid('tax_rate_id')->nullable();

            $table->ulid('financial_account_id')->nullable(); // cash/bank account used
            $table->ulid('cost_center_id')->nullable();

            // Provider / employee
            $table->string('provider_type', 50)->nullable(); // employee | supplier | service_provider | other
            $table->string('provider_id', 26)->nullable();
            $table->string('provider_name', 150)->nullable();

            // Optional linkage to operational entities
            $table->ulid('vehicle_id')->nullable();
            $table->ulid('sales_order_id')->nullable();
            $table->string('mission_reference', 80)->nullable(); // SWO/Mission ref
            $table->string('dwo_reference', 80)->nullable();
            $table->string('related_doc_type', 50)->nullable(); // generic override
            $table->string('related_doc_id', 26)->nullable();

            $table->string('status', 20)->default('draft'); // draft | posted | approved | rejected | cancelled
            $table->timestamp('posted_at')->nullable();
            $table->foreignUlid('posted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('description', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_path', 500)->nullable(); // attachment path
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('expense_date');
            $table->index('financial_period_id');
            $table->index('category');
            $table->index('status');
            $table->index('vehicle_id');
            $table->index('cost_center_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
