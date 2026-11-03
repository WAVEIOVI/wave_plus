<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_variant_option_values', function (Blueprint $table) {
            $table->ulid('variant_id');
            $table->ulid('option_value_id');

            $table->foreign('variant_id')->references('id')->on('catalog_variants')->cascadeOnDelete();
            $table->foreign('option_value_id')->references('id')->on('catalog_option_values')->cascadeOnDelete();

            $table->primary(['variant_id', 'option_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_variant_option_values');
    }
};
