<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_list_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignUlid('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->foreignUlid('variant_id')->nullable()->constrained('catalog_variants')->cascadeOnDelete();
            $table->string('price_computation', 20)->default('fixed');

            $table->decimal('fixed_price', 12, 3)->nullable();
            $table->decimal('discount_pct', 5, 2)->nullable();
            $table->decimal('min_qty', 12, 3)->nullable();
            $table->decimal('max_qty', 12, 3)->nullable();
            $table->decimal('computed_price', 12, 3);
            $table->text('notes')->nullable();
            $table->decimal('internal_cost', 12, 3)->nullable();
            $table->decimal('sales_price', 12, 3)->nullable();

            $table->string('source_type', 20)->nullable()->default('catalog');
            $table->ulid('source_blueprint_id')->nullable();
            $table->json('source_blueprint_option_values')->nullable();
            $table->ulid('source_servicing_id')->nullable();
            $table->ulid('source_consumable_id')->nullable();
            $table->json('calculation_snapshot')->nullable();

            $table->timestamps();

            $table->foreign('source_blueprint_id')->references('id')->on('equipment_blueprints')->nullOnDelete();
            $table->foreign('source_servicing_id')->references('id')->on('blueprint_servicing')->nullOnDelete();
            $table->foreign('source_consumable_id')->references('id')->on('blueprint_servicing_consumables')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_lines');
    }
};
