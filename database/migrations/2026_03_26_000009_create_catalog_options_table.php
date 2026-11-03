<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('catalog_item_id');
            $table->json('name');           // i18n: {en, fr, ar}
            $table->integer('sort_order')->default(0);
            $table->string('ui_control', 30)->default('select');
            $table->timestamps();

            $table->foreign('catalog_item_id')
                ->references('id')
                ->on('catalog_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_options');
    }
};
