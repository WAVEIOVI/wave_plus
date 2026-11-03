<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver Incidents & Violations
 *
 * Tracks driver incidents, accidents, and traffic violations
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_incidents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('incident_number', 50)->unique();
            $table->foreignUlid('driver_id')->constrained('hr_approved_drivers')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUlid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();

            // Incident details
            $table->enum('incident_type', [
                'accident',
                'traffic_violation',
                'parking_violation',
                'speeding',
                'equipment_damage',
                'safety_violation',
                'policy_violation',
                'near_miss',
                'customer_complaint',
                'other',
            ])->index();
            $table->enum('severity', [
                'minor', 'moderate', 'major', 'critical',
            ])->index();

            // Date & location
            $table->dateTime('incident_date');
            $table->string('location', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address', 500)->nullable();

            // Description
            $table->string('title', 200);
            $table->text('description');
            $table->json('incident_details')->nullable();

            // Damage & injury
            $table->boolean('property_damage')->default(false);
            $table->boolean('vehicle_damage')->default(false);
            $table->boolean('personal_injury')->default(false);
            $table->boolean('third_party_involved')->default(false);
            $table->text('damage_description')->nullable();

            // Cost
            $table->decimal('estimated_cost', 10, 3)->nullable();
            $table->decimal('actual_cost', 10, 3)->nullable();
            $table->string('currency', 3)->default('TND');

            // Insurance & authorities
            $table->boolean('police_report_filed')->default(false);
            $table->string('police_report_number', 100)->nullable();
            $table->boolean('insurance_claim_filed')->default(false);
            $table->string('insurance_claim_number', 100)->nullable();
            $table->enum('insurance_claim_status', [
                'pending', 'approved', 'rejected', 'settled',
            ])->nullable();

            // Driver status
            $table->enum('driver_at_fault', [
                'yes', 'no', 'partial', 'under_investigation', 'unknown',
            ])->nullable();
            $table->boolean('alcohol_test_conducted')->default(false);
            $table->boolean('drug_test_conducted')->default(false);
            $table->text('driver_statement')->nullable();

            // Witnesses & documentation
            $table->json('witnesses')->nullable();
            $table->json('documents')->nullable(); // Photos, reports, etc.

            // Follow-up actions
            $table->enum('status', [
                'reported',
                'under_investigation',
                'resolved',
                'closed',
                'pending_action',
            ])->default('reported')->index();
            $table->boolean('requires_driver_training')->default(false);
            $table->boolean('driver_suspended')->default(false);
            $table->date('suspension_until')->nullable();
            $table->text('corrective_actions')->nullable();

            // Context
            $table->ulid('mission_id')->nullable()->index();
            $table->foreignUlid('dwo_id')
                ->nullable()
                ->constrained('delivery_work_orders')
                ->nullOnDelete();
            $table->foreignUlid('swo_id')
                ->nullable()
                ->constrained('service_work_orders')
                ->nullOnDelete();

            // Resolution
            $table->text('resolution_notes')->nullable();
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            // Audit
            $table->foreignUlid('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['driver_id', 'incident_date']);
            $table->index(['vehicle_id', 'incident_date']);
            $table->index(['incident_type', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_incidents');
    }
};
