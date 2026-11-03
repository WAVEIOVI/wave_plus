<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicing_consumables', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('servicing_id');
            $table->ulid('catalog_item_id');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit', 30)->nullable();

            $table->foreign('servicing_id')
                ->references('catalog_item_id')
                ->on('catalog_eq_servicing')
                ->cascadeOnDelete();

            $table->foreign('catalog_item_id')
                ->references('id')
                ->on('catalog_items')
                ->cascadeOnDelete();

            $table->unique(['servicing_id', 'catalog_item_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicing_consumables');
    }
};
