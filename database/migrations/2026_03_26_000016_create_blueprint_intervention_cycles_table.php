<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprint_intervention_cycles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('blueprint_id');

            $table->foreign('blueprint_id')
                ->references('id')
                ->on('equipment_blueprints')
                ->cascadeOnDelete();

            $table->enum('intervention_type', [
                'inspection',
                'refill',
                'hydro_test',
                'maintenance',
                'calibration',
                'replacement',
            ]);

            $table->unsignedInteger('first_interval_months');
            $table->unsignedInteger('recurring_interval_months')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprint_intervention_cycles');
    }
};
