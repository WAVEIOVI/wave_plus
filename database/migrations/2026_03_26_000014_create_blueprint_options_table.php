<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprint_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('blueprint_id');
            $table->json('name');           // i18n: {en, fr, ar} — e.g. "Capacity", "Pressure Type"
            $table->string('code', 60)->nullable();
            $table->string('data_type', 30)->default('select');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('code');

            $table->foreign('blueprint_id')
                ->references('id')
                ->on('equipment_blueprints')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprint_options');
    }
};
