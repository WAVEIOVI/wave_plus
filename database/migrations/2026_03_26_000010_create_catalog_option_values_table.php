<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_option_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('option_id');
            $table->foreign('option_id')->references('id')->on('catalog_options')->cascadeOnDelete();
            $table->json('value');           // i18n: {en, fr, ar}
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_option_values');
    }
};
