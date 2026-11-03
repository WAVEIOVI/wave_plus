<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('po_id')->constrained('purchase_orders')->cascadeOnDelete();

            // Catalog item & variant — nullable to support ad-hoc entries
            $table->foreignUlid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUlid('variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();

            // Ad-hoc item support
            $table->boolean('is_ad_hoc')->default(false);
            $table->string('ad_hoc_name')->nullable();
            $table->string('ad_hoc_sku')->nullable();

            $table->ulid('ipr_line_id')->nullable();
            $table->string('supplier_reference', 100)->nullable();
            $table->string('description_snapshot', 300)->nullable();

            $table->decimal('qty_ordered', 10, 2);
            $table->decimal('unit_price_ht', 12, 3);
            $table->decimal('tax_rate_pct', 5, 2);

            $table->decimal('total_ht', 14, 3);
            $table->decimal('total_tax', 14, 3);
            $table->decimal('total_ttc', 14, 3);

            $table->decimal('qty_received', 10, 2)->default(0);
            $table->enum('fulfillment_status', ['pending', 'partially_received', 'fully_received', 'over_received'])->default('pending');
            $table->date('expected_delivery_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_lines');
    }
};
