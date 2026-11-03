<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bp_bank_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // ── Polymorphic Owner ────────────────────────────────────────
            $table->string('bankable_type');
            $table->ulid('bankable_id');
            $table->index(['bankable_type', 'bankable_id']);

            // ── Bank Details ─────────────────────────────────────────────
            $table->string('bank_name');
            $table->string('bank_code', 10)->nullable();
            $table->string('branch')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('rib', 23)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('swift_bic', 11)->nullable();
            $table->string('account_number')->nullable();
            $table->string('currency', 10)->default('TND');

            // ── Flags ────────────────────────────────────────────────────
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->enum('usage', ['payments', 'receipts', 'both'])->default('both');

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bp_bank_accounts');
    }
};
