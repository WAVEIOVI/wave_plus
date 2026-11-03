<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery Provider Activity Log
 *
 * Tracks delivery provider operational history and performance events
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_provider_activity_log', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('delivery_provider_id')
                ->constrained('delivery_service_providers')
                ->cascadeOnDelete();

            // Event details
            $table->enum('event_type', [
                'contract_signed',
                'contract_renewed',
                'contract_terminated',
                'status_changed',
                'delivery_assigned',
                'delivery_completed',
                'delivery_failed',
                'delivery_delayed',
                'incident_reported',
                'performance_review',
                'score_updated',
                'capacity_changed',
                'rate_updated',
                'sla_violation',
                'complaint_received',
                'rating_received',
                'document_updated',
                'other',
            ]);
            $table->string('event_title', 200);
            $table->text('event_description')->nullable();
            $table->dateTime('event_date');

            // Context
            $table->string('old_value', 200)->nullable();
            $table->string('new_value', 200)->nullable();
            $table->json('metadata')->nullable();

            // Related DWO (if applicable)
            $table->foreignUlid('dwo_id')
                ->nullable()
                ->constrained('delivery_work_orders')
                ->nullOnDelete();

            // Performance impact
            $table->enum('impact', [
                'positive', 'neutral', 'negative',
            ])->nullable();
            $table->decimal('score_change', 5, 2)->nullable();

            // Additional info
            $table->text('notes')->nullable();

            // Audit
            $table->foreignUlid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['delivery_provider_id', 'event_date'], 'dpa_provider_date_idx');
            $table->index(['delivery_provider_id', 'event_type'], 'dpa_provider_event_idx');
            $table->index('event_type', 'dpa_event_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_provider_activity_log');
    }
};
