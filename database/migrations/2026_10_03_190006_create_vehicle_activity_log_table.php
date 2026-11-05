<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle Activity Tracker
 *
 * Timeline of all vehicle events and activities
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_activity_log', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();

            // Event details
            $table->enum('event_type', [
                'created',
                'status_change',
                'assignment',
                'assignment_end',
                'reassignment',
                'fuel',
                'maintenance',
                'inspection',
                'checklist',
                'incident',
                'document_update',
                'rental_start',
                'rental_end',
                'client_use_start',
                'client_use_end',
                'mission_start',
                'mission_end',
                'location_change',
                'odometer_update',
                'odometer_correction',
                'archived',
                'deleted',
                'other',
            ])->index();
            $table->string('event_title', 200);
            $table->text('event_description')->nullable();
            $table->dateTime('event_date');

            // Context
            $table->string('old_value', 200)->nullable();
            $table->string('new_value', 200)->nullable();
            $table->json('metadata')->nullable();

            // Related records
            $table->ulid('related_id')->nullable()->index();
            $table->string('related_type', 100)->nullable();

            // Actor
            $table->foreignUlid('actor_employee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('actor_name', 200)->nullable();

            // Location
            $table->string('location', 200)->nullable();
            $table->unsignedInteger('odometer_km')->nullable();

            // Audit
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'event_date']);
            $table->index(['vehicle_id', 'event_type']);
            $table->index(['related_id', 'related_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_activity_log');
    }
};
