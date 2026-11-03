<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->ulid('equipment_id')->nullable();
            $table->ulid('blueprint_id')->nullable();
            $table->string('service_type', 50)->default('maintenance');
            $table->string('status', 40)->default('pending');
            $table->string('result', 30)->nullable(); // pass | fail | conditional_pass
            $table->text('technician_notes')->nullable();
            $table->text('technical_observations')->nullable();
            $table->json('measurements')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('equipment_id')->references('id')->on('client_equipment')->nullOnDelete();
            $table->foreign('blueprint_id')->references('id')->on('equipment_blueprints')->nullOnDelete();

            $table->index(['mission_id', 'status']);
            $table->index('equipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
