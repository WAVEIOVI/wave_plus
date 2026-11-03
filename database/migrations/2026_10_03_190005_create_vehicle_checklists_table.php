<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle Checklists
 *
 * Stores completed vehicle inspection checklists (pre-trip, post-trip, periodic)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_checklists', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->ulid('checklist_template_id')->nullable();
            $table->foreign('checklist_template_id')
                ->references('id')
                ->on('checklist_templates')
                ->nullOnDelete();

            // Checklist details
            $table->enum('checklist_type', [
                'pre_trip',
                'post_trip',
                'daily_inspection',
                'weekly_inspection',
                'monthly_inspection',
                'handover',
                'return',
                'other',
            ]);
            $table->string('title', 200);

            // When & who
            $table->dateTime('completed_at');
            $table->foreignUlid('completed_by_employee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUlid('driver_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Context
            $table->unsignedInteger('odometer_km')->nullable();
            $table->string('location', 200)->nullable();

            // Results
            $table->json('checklist_items'); // Array of check items with results
            $table->enum('overall_status', [
                'passed',
                'passed_with_notes',
                'failed',
                'requires_attention',
                'incomplete',
            ])->index();
            $table->unsignedSmallInteger('items_passed')->default(0);
            $table->unsignedSmallInteger('items_failed')->default(0);
            $table->unsignedSmallInteger('items_total')->default(0);

            // Issues found
            $table->boolean('has_issues')->default(false)->index();
            $table->json('issues_found')->nullable();
            $table->text('notes')->nullable();
            $table->text('recommendations')->nullable();

            // Follow-up
            $table->boolean('requires_maintenance')->default(false);
            $table->foreignUlid('maintenance_record_id')
                ->nullable()
                ->constrained('vehicle_maintenance_records')
                ->nullOnDelete();

            // Mission context (if applicable)
            $table->ulid('mission_id')->nullable()->index();
            $table->foreignUlid('dwo_id')
                ->nullable()
                ->constrained('delivery_work_orders')
                ->nullOnDelete();
            $table->foreignUlid('swo_id')
                ->nullable()
                ->constrained('service_work_orders')
                ->nullOnDelete();

            // Audit
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'checklist_type']);
            $table->index(['vehicle_id', 'completed_at']);
            $table->index(['completed_by_employee_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_checklists');
    }
};
