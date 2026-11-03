<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervention_materials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('intervention_id')->constrained('interventions')->cascadeOnDelete();
            $table->ulid('catalog_item_id');
            $table->ulid('catalog_variant_id')->nullable();
            $table->string('description_snapshot');
            $table->decimal('qty_used', 10, 3)->default(1);
            $table->string('uom_code_snapshot', 30)->nullable();
            $table->string('variant_sku_snapshot', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('catalog_item_id')->references('id')->on('catalog_items')->restrictOnDelete();
            $table->foreign('catalog_variant_id')->references('id')->on('catalog_variants')->nullOnDelete();
            $table->index(['intervention_id', 'catalog_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_materials');
    }
};
