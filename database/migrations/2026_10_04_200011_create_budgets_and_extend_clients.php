<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 150);
            $table->string('type', 30)->default('annual'); // annual | quarterly | monthly | project
            $table->smallInteger('year');
            $table->tinyInteger('month')->nullable(); // for monthly budgets
            $table->tinyInteger('quarter')->nullable(); // for quarterly budgets
            $table->ulid('cost_center_id')->nullable();
            $table->decimal('total_budget', 16, 3)->default(0);
            $table->decimal('total_spent', 16, 3)->default(0);
            $table->decimal('total_remaining', 16, 3)->default(0);
            $table->string('status', 20)->default('draft'); // draft | approved | active | closed
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('year');
            $table->index('status');
            $table->index('cost_center_id');
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('budget_id');
            $table->string('category', 100);
            $table->string('description', 255)->nullable();
            $table->decimal('budgeted_amount', 16, 3)->default(0);
            $table->decimal('actual_amount', 16, 3)->default(0);
            $table->decimal('variance', 16, 3)->default(0); // budgeted - actual
            $table->timestamps();

            $table->index('budget_id');
        });

        // Add financial fields to clients table for credit management
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'credit_limit')) {
                $table->decimal('credit_limit', 16, 3)->nullable()->after('payment_terms')->comment('Maximum allowed outstanding AR');
            }
            if (! Schema::hasColumn('clients', 'credit_currency')) {
                $table->string('credit_currency', 3)->default('TND')->after('credit_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumnIfExists('credit_limit');
            $table->dropColumnIfExists('credit_currency');
        });
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
    }
};
