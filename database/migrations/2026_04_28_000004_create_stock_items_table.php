<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUlid('catalog_variant_id')->constrained('catalog_variants')->restrictOnDelete();
            $table->foreignUlid('location_id')->constrained('warehouse_locations')->restrictOnDelete();
            $table->ulid('stock_lot_id')->nullable();
            $table->foreign('stock_lot_id')->references('id')->on('stock_lots')->nullOnDelete();

            $table->string('stock_lot_id_uni', 26)->virtualAs("COALESCE(stock_lot_id, 'NO-LOT')");

            $table->decimal('qty_on_hand', 14, 4)->default(0);
            $table->decimal('qty_reserved', 14, 4)->default(0);
            $table->decimal('qty_available', 14, 4)->default(0);
            $table->decimal('qty_in_transit', 14, 4)->default(0);

            $table->decimal('unit_cost', 12, 3)->nullable();
            $table->string('currency', 3)->default('TND');

            $table->enum('condition', ['new', 'used', 'refurbished', 'damaged'])->default('new');
            $table->enum('status', ['active', 'quarantined', 'blocked'])->default('active')->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['catalog_variant_id', 'location_id', 'stock_lot_id_uni'], 'stock_items_variant_loc_lot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
