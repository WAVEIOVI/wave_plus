<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver Activity Log
 *
 * Timeline tracker for driver operational history and events
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_activity_log', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('driver_id')->constrained('hr_approved_drivers')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();

            // Event details
            $table->enum('event_type', [
                'authorization_granted',
                'authorization_renewed',
                'authorization_suspended',
                'authorization_revoked',
                'authorization_expired',
                'authorization_reinstated',
                'vehicle_assigned',
                'vehicle_returned',
                'mission_started',
                'mission_completed',
                'training_completed',
                'certification_earned',
                'incident_reported',
                'violation_recorded',
                'checklist_completed',
                'document_updated',
                'review_conducted',
                'score_updated',
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
            $table->foreignUlid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->ulid('related_id')->nullable()->index();
            $table->string('related_type', 100)->nullable();

            // Impact
            $table->enum('impact', [
                'positive', 'neutral', 'negative',
            ])->nullable();
            $table->decimal('score_change', 5, 2)->nullable();

            // Audit
            $table->foreignUlid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['driver_id', 'event_date']);
            $table->index(['driver_id', 'event_type']);
            $table->index(['employee_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_activity_log');
    }
};
