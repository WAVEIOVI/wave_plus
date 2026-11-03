<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprint_guideline', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('equipment_blueprint_id');
            $table->foreignUlid('guideline_id')
                ->constrained('guidelines')
                ->cascadeOnDelete();

            $table->foreign('equipment_blueprint_id')
                ->references('id')
                ->on('equipment_blueprints')
                ->cascadeOnDelete();

            $table->unique(['equipment_blueprint_id', 'guideline_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprint_guideline');
    }
};
