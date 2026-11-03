<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('catalog_item_id')->constrained('catalog_items')->cascadeOnDelete();

            $table->boolean('is_default')->default(false);
            $table->string('name')->nullable();
            $table->string('sku')->unique()->nullable();
            $table->string('barcode', 100)->nullable()->unique();

            $table->decimal('price', 12, 3)->nullable();
            $table->decimal('cost_price', 12, 3)->nullable();
            $table->decimal('markup', 6, 4)->nullable();
            $table->enum('tax_class', ['standard_19pct', 'reduced_7pct', 'exempt'])->default('standard_19pct');

            // Inventory Settings (Quantities moved to stock_items)
            $table->decimal('min_stock_level', 10, 2)->nullable();
            $table->decimal('reorder_point', 10, 2)->nullable();
            $table->decimal('reorder_qty', 10, 2)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_variants');
    }
};
