<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal Entries (GL foundation)
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('entry_number', 40)->unique();
            $table->ulid('financial_period_id')->nullable();
            $table->date('entry_date');
            $table->string('type', 30)->default('journal'); // journal | adjustment | opening | closing
            $table->string('reference', 100)->nullable();
            $table->string('source_type', 80)->nullable(); // App\Modules\Finance\Models\SalesInvoice etc.
            $table->ulid('source_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->string('currency', 3)->default('TND');
            $table->decimal('total_debit', 16, 3)->default(0);
            $table->decimal('total_credit', 16, 3)->default(0);
            $table->string('status', 20)->default('draft'); // draft | posted | reversed
            $table->timestamp('posted_at')->nullable();
            $table->foreignUlid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('financial_period_id');
            $table->index('entry_date');
            $table->index(['source_type', 'source_id']);
            $table->index('status');
        });

        // Journal Lines (debit/credit entries)
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('journal_entry_id');
            $table->ulid('coa_account_id');
            $table->decimal('debit', 16, 3)->default(0);
            $table->decimal('credit', 16, 3)->default(0);
            $table->ulid('cost_center_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index('journal_entry_id');
            $table->index('coa_account_id');
            $table->index('cost_center_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
    }
};
