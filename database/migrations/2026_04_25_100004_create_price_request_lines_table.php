<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_request_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('price_request_id')->constrained('price_requests')->cascadeOnDelete();
            $table->foreignUlid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUlid('variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();
            $table->boolean('is_ad_hoc')->default(false);
            $table->string('ad_hoc_name')->nullable();
            $table->string('ad_hoc_sku', 100)->nullable();

            $table->decimal('qty_requested', 12, 3);
            $table->decimal('internal_cost', 12, 3)->nullable();
            $table->decimal('target_margin_pct', 5, 2)->nullable();
            $table->decimal('suggested_price', 12, 3);
            $table->decimal('proposed_price', 12, 3)->nullable();
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('tva_rate_pct', 5, 2)->default(0)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('actual_margin_pct', 5, 2)->default(0);

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
        Schema::dropIfExists('price_request_lines');
    }
};
