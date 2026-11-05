<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle Maintenance Records
 *
 * Tracks preventive and corrective maintenance for fleet vehicles
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_maintenance_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('maintenance_number', 50)->unique();
            $table->foreignUlid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();

            // Maintenance type
            $table->enum('maintenance_type', [
                'preventive',
                'corrective',
                'inspection',
                'repair',
                'overhaul',
                'warranty',
                'recall',
                'other',
            ]);
            $table->enum('priority', [
                'low', 'normal', 'medium', 'high', 'urgent', 'critical',
            ])->default('normal');

            // Schedule
            $table->date('scheduled_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->unsignedInteger('odometer_km')->nullable();

            // Workshop/Provider
            $table->foreignUlid('workshop_provider_id')
                ->nullable()
                ->constrained('service_providers')
                ->nullOnDelete();
            $table->foreignUlid('workshop_unit_id')
                ->nullable()
                ->constrained('workshop_units')
                ->nullOnDelete();
            $table->string('workshop_name', 200)->nullable();

            // Work performed
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('work_items')->nullable(); // Array of tasks performed
            $table->json('parts_used')->nullable(); // Array of parts/materials

            // Cost
            $table->decimal('labor_cost', 10, 3)->nullable();
            $table->decimal('parts_cost', 10, 3)->nullable();
            $table->decimal('total_cost', 10, 3)->nullable();
            $table->string('currency', 3)->default('TND');

            // Invoice
            $table->string('invoice_number', 100)->nullable();
            $table->date('invoice_date')->nullable();

            // Next service
            $table->date('next_service_date')->nullable();
            $table->unsignedInteger('next_service_km')->nullable();

            // Status
            $table->enum('status', [
                'scheduled',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('scheduled')->index();

            // Additional info
            $table->text('notes')->nullable();
            $table->json('documents')->nullable(); // Array of document paths

            // Audit
            $table->foreignUlid('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'maintenance_type']);
            $table->index(['vehicle_id', 'status']);
            $table->index('scheduled_date');
            $table->index('next_service_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenance_records');
    }
};
