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
        Schema::create('rfq_responses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('rfq_id')->constrained('rfq_headers')->cascadeOnDelete();
            $table->foreignUlid('supplier_id')->constrained('suppliers')->cascadeOnDelete();

            // Bid Information
            $table->timestamp('submitted_at');
            $table->date('validity_until')->nullable();
            $table->date('can_deliver_by')->nullable();
            $table->enum('payment_terms_offered', [
                'net_15', 'net_30', 'net_60', 'net_90', 'immediate', 'other',
            ])->nullable();

            // Financials and Scoring
            $table->decimal('total_ht', 14, 3)->default(0);
            $table->decimal('evaluation_score', 5, 2)->nullable();
            $table->json('evaluation_details')->nullable();
            $table->boolean('is_awarded')->default(false);

            // Additional Context
            $table->text('supplier_notes')->nullable();

            $table->timestamps();

            // Constraints
            $table->unique(['rfq_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfq_responses');
    }
};
