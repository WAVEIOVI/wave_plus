<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_item_suppliers', function (Blueprint $table) {
            $table->ulid('catalog_item_id');
            $table->ulid('supplier_id');

            $table->primary(['catalog_item_id', 'supplier_id']);

            $table->foreign('catalog_item_id')
                ->references('id')
                ->on('catalog_items')
                ->cascadeOnDelete();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->cascadeOnDelete();

            $table->string('supplier_reference', 100)->nullable();
            $table->decimal('supplier_price', 12, 3)->nullable();
            $table->string('currency', 3)->default('TND');
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->decimal('min_order_qty', 10, 2)->nullable();
            $table->boolean('is_preferred')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_item_suppliers');
    }
};
