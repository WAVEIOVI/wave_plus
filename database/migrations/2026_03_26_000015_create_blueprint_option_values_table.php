<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprint_option_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('option_id');
            $table->foreign('option_id')->references('id')->on('blueprint_options')->cascadeOnDelete();
            $table->json('value');           // i18n: {en, fr, ar} — e.g. "6kg", "9kg", "Stored Pressure"
            $table->string('code', 60)->nullable();
            $table->decimal('numeric_value', 10, 4)->nullable();
            $table->string('unit', 30)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprint_option_values');
    }
};
