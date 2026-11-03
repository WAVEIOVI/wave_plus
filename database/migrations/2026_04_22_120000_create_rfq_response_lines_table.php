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
        Schema::create('rfq_response_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('rfq_response_id')->constrained('rfq_responses')->cascadeOnDelete();
            $table->foreignUlid('rfq_line_id')->constrained('rfq_lines')->cascadeOnDelete();

            // ── Offered Bid Values ──────────────────────────────────────
            $table->decimal('offered_unit_price_ht', 12, 3);
            $table->decimal('offered_qty', 10, 2);
            $table->integer('offered_lead_time_days')->nullable();
            $table->json('certifications_confirmed')->nullable();

            // ── Computed / Analysis ─────────────────────────────────────
            $table->decimal('vs_target_pct', 5, 2)->nullable()
                ->comment('Percentage variance vs. target price: ((offered - target) / target) * 100');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfq_response_lines');
    }
};
