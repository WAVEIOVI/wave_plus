<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Level 1: Blueprint ↔ Eq Servicing items ─────────────────────
        Schema::create('blueprint_servicing', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('blueprint_id');
            $table->ulid('servicing_item_id'); // FK → catalog_items (eq_servicing)
            $table->json('applicability_conditions')->nullable();

            $table->foreign('blueprint_id')
                ->references('id')
                ->on('equipment_blueprints')
                ->cascadeOnDelete();

            $table->foreign('servicing_item_id')
                ->references('catalog_item_id')
                ->on('catalog_eq_servicing')
                ->cascadeOnDelete();

            $table->unique(['blueprint_id', 'servicing_item_id'], 'bp_svc_unique');
            $table->timestamps();
        });

        // ── Level 2: Each servicing row's required consumables ──────────
        Schema::create('blueprint_servicing_consumables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('blueprint_servicing_id'); // FK → blueprint_servicing
            $table->ulid('consumable_item_id');     // FK → catalog_items (consumable)

            $table->foreign('blueprint_servicing_id')
                ->references('id')
                ->on('blueprint_servicing')
                ->cascadeOnDelete();

            $table->foreign('consumable_item_id')
                ->references('id')
                ->on('catalog_items')
                ->cascadeOnDelete();

            // ── Quantity Calculation Engine ──────────────────────────────
            $table->string('calculation_method', 50)->default('multiply');
            $table->decimal('quantity_multiplier', 10, 4)->default(1); // e.g. 1.0 for fixed, 1.0 per kg for capacity
            $table->string('calculation_type', 30)->default('fixed');
            $table->ulid('calculation_source_option_id')->nullable();
            $table->decimal('calculation_parameter', 10, 4)->default(1);
            $table->decimal('fixed_quantity', 10, 4)->default(1);
            $table->json('applicability_conditions')->nullable();

            $table->unique(['blueprint_servicing_id', 'consumable_item_id'], 'bsc_unique');
            $table->timestamps();

            $table->foreign('calculation_source_option_id', 'bsc_source_opt_fk')
                ->references('id')->on('blueprint_options')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprint_servicing_consumables');
        Schema::dropIfExists('blueprint_servicing');
    }
};
