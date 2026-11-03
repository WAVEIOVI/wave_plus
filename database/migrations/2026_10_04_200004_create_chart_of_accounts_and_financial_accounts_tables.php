<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of Accounts
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('account_type', 30); // asset | liability | equity | revenue | expense
            $table->string('sub_type', 50)->nullable(); // cash | bank | receivable | payable | inventory | etc.
            $table->ulid('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // system accounts cannot be deleted
            $table->text('description')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('account_type');
            $table->index('is_active');
        });

        // Financial Accounts (cash, bank, petty cash — operational)
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('type', 30); // cash | bank | petty_cash | other
            $table->string('currency', 3)->default('TND');
            $table->decimal('opening_balance', 16, 3)->default(0);
            $table->decimal('current_balance', 16, 3)->default(0);
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 80)->nullable();
            $table->string('iban', 40)->nullable();
            $table->string('swift_bic', 15)->nullable();
            $table->string('status', 20)->default('active'); // active | inactive | closed
            $table->string('reconciliation_status', 20)->default('unreconciled'); // unreconciled | reconciled | in_progress
            $table->date('last_reconciled_date')->nullable();
            $table->ulid('coa_account_id')->nullable(); // link to chart of accounts
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
        Schema::dropIfExists('chart_of_accounts');
    }
};
