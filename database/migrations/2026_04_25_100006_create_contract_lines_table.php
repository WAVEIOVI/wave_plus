<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignUlid('catalog_item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUlid('variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();
            $table->boolean('is_ad_hoc')->default(false);
            $table->string('ad_hoc_name')->nullable();
            $table->string('ad_hoc_sku', 100)->nullable();

            $table->decimal('contracted_qty', 12, 3)->nullable();
            $table->decimal('contracted_unit_price', 12, 3)->nullable();
            $table->decimal('called_off_qty', 12, 3)->default(0);
            $table->decimal('remaining_qty', 12, 3)->nullable();
            $table->integer('sort_order')->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_lines');
    }
};
