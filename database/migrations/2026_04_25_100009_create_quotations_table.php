<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 40)->unique();
            $table->unsignedInteger('version')->default(1);
            $table->ulid('parent_quotation_id')->nullable();
            $table->ulid('pr_id')->nullable();
            $table->foreignUlid('client_id')->constrained('clients')->restrictOnDelete();
            $table->ulid('site_id')->nullable();
            $table->ulid('contract_id')->nullable();
            $table->date('valid_until');
            $table->string('currency', 3)->default('TND');
            $table->enum('payment_terms', ['immediate', 'net_15', 'net_30', 'net_60', 'advance_50', 'split', 'monthly', 'quarterly', 'semi_annual', 'annual', 'per_order']);
            $table->decimal('global_discount_pct', 5, 2)->nullable();
            $table->decimal('total_ht', 14, 3)->default(0);
            $table->decimal('total_tva', 14, 3)->default(0);
            $table->decimal('total_ttc', 14, 3)->default(0);
            $table->boolean('approval_required')->default(false);
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->ulid('converted_to_so_id')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('cover_letter')->nullable();
            $table->enum('status', ['draft', 'sent', 'under_review', 'accepted', 'rejected', 'expired', 'converted'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            // Self-referencing FK for versioning
            $table->foreign('parent_quotation_id')->references('id')->on('quotations')->nullOnDelete();
            // FK to price_requests
            $table->foreign('pr_id')->references('id')->on('price_requests')->nullOnDelete();
            $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
            $table->foreign('converted_to_so_id')->references('id')->on('sales_orders')->nullOnDelete();

            $table->index('status');
        });

        Schema::table('price_requests', function (Blueprint $table) {
            $table->foreign('converted_to_quotation_id')->references('id')->on('quotations')->nullOnDelete();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreign('quotation_id')->references('id')->on('quotations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
