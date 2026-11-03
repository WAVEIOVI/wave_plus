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
        Schema::create('rfq_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('rfq_id')->constrained('rfq_headers')->cascadeOnDelete();
            $table->foreignUlid('supplier_id')->constrained('suppliers')->cascadeOnDelete();

            // ── Tracking ─────────────────────────────────────────────────
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();

            // ── Status ───────────────────────────────────────────────────
            $table->enum('status', [
                'sent',
                'viewed',
                'responded',
                'awarded',
                'declined',
                'no_response',
            ])->default('sent');
            $table->text('decline_reason')->nullable();

            $table->timestamps();

            // ── Constraints ──────────────────────────────────────────────
            $table->unique(['rfq_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfq_invitations');
    }
};
