<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_eq_servicing', function (Blueprint $table) {
            // PK = FK to catalog_items (1-to-1 extension)
            $table->ulid('catalog_item_id')->primary();
            $table->foreign('catalog_item_id')
                ->references('id')
                ->on('catalog_items')
                ->cascadeOnDelete();

            // ── Servicing Type & Delivery ────────────────────────────────
            $table->enum('eq_servicing_type', [
                'inspection_and_testing',
                'maintenance_and_repair',
                'refill_recharge',
                'calibration_and_certification',
                'installation_decommissioning',
            ]);

            $table->enum('delivery_mode', ['on_site', 'workshop', 'hybrid'])->nullable();

            $table->enum('billable_unit', [
                'per_unit',
                'per_session',
                'per_site',
                'flat_fee',
            ])->nullable();

            $table->unsignedInteger('duration_minutes')->nullable();

            // ── Recurrence & Scope ──────────────────────────────────────
            $table->boolean('is_recurring')->default(false);
            // ── Certifications ──────────────────────────────────────────
            $table->json('required_certifications')->nullable();

            // ── Technical Specifications ───────────────────────────────
            $table->json('technical_specs')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_eq_servicing');
    }
};
