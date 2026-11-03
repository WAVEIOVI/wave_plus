<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle Assignment and Custody History
 *
 * Tracks vehicle responsibility, location, and custody changes over time
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();

            // Assignment details
            $table->foreignUlid('responsible_employee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->enum('custody_type', [
                'company_site',
                'employee_home',
                'mission',
                'maintenance',
                'rental',
                'client',
                'storage',
                'other',
            ]);
            $table->foreignUlid('site_id')
                ->nullable()
                ->constrained('warehouse_sites')
                ->nullOnDelete();
            $table->text('location_notes')->nullable();

            // Period
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->boolean('is_current')->default(false)->index();

            // Context
            $table->string('reason', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('odometer_at_start')->nullable();
            $table->unsignedInteger('odometer_at_end')->nullable();

            // Audit
            $table->foreignUlid('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUlid('returned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'is_current']);
            $table->index(['responsible_employee_id', 'is_current']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_assignments');
    }
};
