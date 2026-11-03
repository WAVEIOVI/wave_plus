<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->json('name');                          // i18n: {en, fr, ar}
            $table->ulid('parent_id')->nullable();
            $table->json('item_type_scope')->nullable();   // ["product","consumable","eq_servicing"]
            $table->json('item_subtype_scope')->nullable();
            $table->string('icon', 20)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')
                ->on('catalog_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_categories');
    }
};
