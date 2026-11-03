<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rfq_headers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference')->unique();

            // ── Source ────────────────────────────────────────────────────
            $table->json('ipr_ids')->nullable(); // Array of source IPR IDs

            // ── Core Fields ──────────────────────────────────────────────
            $table->string('title', 200);
            $table->dateTime('response_deadline');
            $table->date('required_delivery_date')->nullable();
            $table->json('evaluation_criteria')->nullable(); // e.g. {"price_weight":60, "lead_time_weight":40}
            $table->text('terms_and_conditions')->nullable();

            // ── Status ───────────────────────────────────────────────────
            $table->enum('status', [
                'draft',
                'sent',
                'awaiting_responses',
                'under_evaluation',
                'awarded',
                'cancelled',
            ])->default('draft')->index();

            // ── Award ────────────────────────────────────────────────────
            $table->foreignUlid('awarded_to_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->timestamp('awarded_at')->nullable();
            $table->foreignUlid('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->ulid('converted_to_po_id')->nullable(); // no constraint — purchase_orders table may not exist yet

            // ── Audit ────────────────────────────────────────────────────
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfq_headers');
    }
};
