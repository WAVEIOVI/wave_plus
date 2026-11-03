<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_blueprints', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // ── Identity & Status ────────────────────────────────────────
            $table->string('code', 60)->unique();
            $table->json('name');                              // i18n
            $table->json('description')->nullable();           // i18n
            $table->enum('status', ['active', 'draft', 'inactive'])->default('draft');
            $table->ulid('equipment_class_id')->nullable();

            $table->foreign('equipment_class_id')
                ->references('id')
                ->on('equipment_classes')
                ->nullOnDelete();

            // ── Technical Specifications ─────────────────────────────────
            $table->decimal('working_pressure_bar', 6, 2)->nullable();
            $table->string('fire_class_rating', 40)->nullable();
            $table->unsignedInteger('shelf_life_years')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_blueprints');
    }
};
