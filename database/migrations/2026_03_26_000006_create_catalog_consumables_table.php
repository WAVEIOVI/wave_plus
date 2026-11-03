<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_consumables', function (Blueprint $table) {
            // PK = FK to catalog_items (1-to-1 extension)
            $table->ulid('catalog_item_id')->primary();
            $table->foreign('catalog_item_id')
                ->references('id')
                ->on('catalog_items')
                ->cascadeOnDelete();

            // ── Consumable Classification ────────────────────────────────
            $table->enum('consumable_type', [
                'agents_and_disposables',
                'replacement_parts',
                'safety_signage',
                'accessories',
            ]);

            // ── Part Identity & Consumption ──────────────────────────────
            $table->boolean('is_consumable')->default(true);
            // ── Storage & Shelf Life ─────────────────────────────────────
            $table->unsignedInteger('shelf_life_months')->nullable();
            $table->string('storage_conditions', 200)->nullable();

            // ── Technical Specifications ───────────────────────────────
            $table->json('technical_specs')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_consumables');
    }
};
