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
        Schema::create('ipr_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ipr_id')->constrained('internal_purchase_requests')->cascadeOnDelete();

            // Catalog item & variant — nullable to support ad-hoc (non-catalog) entries
            $table->foreignUlid('catalog_item_id')->nullable()->constrained('catalog_items');
            $table->foreignUlid('variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();

            // Ad-hoc item support
            $table->boolean('is_ad_hoc')->default(false);
            $table->string('ad_hoc_name')->nullable();
            $table->string('ad_hoc_sku')->nullable()->default('NEW');

            // Quantities
            $table->decimal('qty_requested', 10, 2);
            $table->decimal('qty_current_stock', 10, 2)->nullable();
            $table->decimal('qty_on_order', 10, 2)->nullable();

            // Supplier & cost
            $table->foreignUlid('suggested_supplier_id')->nullable()->constrained('suppliers');
            $table->decimal('estimated_unit_cost', 12, 3)->nullable();
            $table->decimal('estimated_total_ht', 14, 3)->virtualAs('qty_requested * estimated_unit_cost')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ipr_lines');
    }
};
