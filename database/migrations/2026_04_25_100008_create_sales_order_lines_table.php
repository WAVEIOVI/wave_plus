<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignUlid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUlid('variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();
            $table->boolean('is_ad_hoc')->default(false);
            $table->string('ad_hoc_name')->nullable();
            $table->string('ad_hoc_sku', 100)->nullable();

            $table->enum('item_type_snapshot', ['physical_product', 'service', 'spare_part']);
            $table->string('description_snapshot')->nullable();
            $table->string('sku_snapshot', 100)->nullable();
            $table->string('uom_code_snapshot', 30)->nullable();
            $table->decimal('qty', 12, 3);
            $table->decimal('reserved_qty', 12, 3)->default(0);
            $table->decimal('fulfilled_qty', 12, 3)->default(0);
            $table->ulid('unit_id')->nullable();
            $table->decimal('unit_price', 12, 3)->nullable();
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('total_ht', 14, 3)->default(0);
            $table->decimal('total_tva', 14, 3)->default(0);
            $table->decimal('total_ttc', 14, 3)->default(0);
            $table->json('target_equipment_ids')->nullable();
            $table->ulid('target_site_id')->nullable();
            $table->string('fulfillment_status', 50)->default('pending');
            $table->date('delivery_date')->nullable();

            $table->string('source_type', 20)->nullable()->default('catalog');
            $table->ulid('source_blueprint_id')->nullable();
            $table->json('source_blueprint_option_values')->nullable();
            $table->ulid('source_servicing_id')->nullable();
            $table->ulid('source_consumable_id')->nullable();
            $table->json('calculation_snapshot')->nullable();

            $table->timestamps();

            $table->foreign('unit_id')->references('id')->on('units_of_measure')->restrictOnDelete();
            $table->foreign('source_blueprint_id')->references('id')->on('equipment_blueprints')->nullOnDelete();
            $table->foreign('source_servicing_id')->references('id')->on('blueprint_servicing')->nullOnDelete();
            $table->foreign('source_consumable_id')->references('id')->on('blueprint_servicing_consumables')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
    }
};
