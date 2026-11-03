<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignUlid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUlid('variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();
            $table->boolean('is_ad_hoc')->default(false);
            $table->string('ad_hoc_name')->nullable();
            $table->string('ad_hoc_sku', 100)->nullable();

            $table->string('description_override')->nullable();
            $table->string('description_snapshot')->nullable();
            $table->string('sku_snapshot', 100)->nullable();
            $table->string('uom_code_snapshot', 30)->nullable();
            $table->decimal('qty', 12, 3);
            $table->decimal('internal_cost', 15, 3)->nullable();
            $table->decimal('actual_margin_pct', 5, 2)->nullable();
            $table->decimal('unit_price', 12, 3);
            $table->decimal('discount_pct', 5, 2)->nullable();
            $table->decimal('unit_price_after_discount', 12, 3);
            $table->ulid('tax_class_id')->nullable();
            $table->decimal('tva_rate_pct', 5, 2)->default(19.00);
            $table->decimal('total_ht', 14, 3)->default(0);
            $table->decimal('total_tva', 14, 3)->default(0);
            $table->decimal('total_ttc', 14, 3)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('note')->nullable();
            $table->enum('item_type_snapshot', ['physical_product', 'service', 'spare_part', 'product', 'consumable', 'eq_servicing']);

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
        Schema::dropIfExists('quotation_lines');
    }
};
