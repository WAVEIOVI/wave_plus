<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR Approved Drivers
 *
 * Manages driver authorization and licensing for employees
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_approved_drivers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('driver_authorization_number', 50)->unique();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();

            // Authorization status
            $table->enum('authorization_status', [
                'pending',
                'approved',
                'suspended',
                'expired',
                'revoked',
                'under_review',
            ])->default('pending')->index();
            $table->date('authorization_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('review_date')->nullable();

            // License information
            $table->string('license_number', 100)->nullable();
            $table->string('license_category', 50)->nullable(); // e.g., B, C, D, etc.
            $table->json('license_categories')->nullable(); // Multiple categories
            $table->date('license_issue_date')->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->string('license_issuer', 100)->nullable();

            // Permitted vehicle types/categories
            $table->json('permitted_vehicle_types')->nullable(); // Array of vehicle types
            $table->json('permitted_vehicle_classes')->nullable(); // Array of vehicle classes
            $table->boolean('can_drive_rental')->default(true);
            $table->boolean('can_drive_client_vehicles')->default(false);

            // Restrictions
            $table->json('restrictions')->nullable(); // e.g., no night driving, max distance
            $table->unsignedSmallInteger('max_passengers')->nullable();
            $table->unsignedInteger('max_cargo_kg')->nullable();
            $table->text('restriction_notes')->nullable();

            // Training & certification
            $table->date('last_training_date')->nullable();
            $table->date('next_training_date')->nullable();
            $table->json('certifications')->nullable(); // Defensive driving, hazmat, etc.

            // Performance & incidents
            $table->unsignedSmallInteger('incident_count')->default(0);
            $table->unsignedSmallInteger('violation_count')->default(0);
            $table->date('last_incident_date')->nullable();
            $table->decimal('driver_score', 5, 2)->nullable(); // Calculated score
            $table->enum('driver_level', [
                'trainee', 'junior', 'standard', 'senior', 'expert',
            ])->default('standard');

            // Suspension history
            $table->date('suspended_from')->nullable();
            $table->date('suspended_until')->nullable();
            $table->text('suspension_reason')->nullable();

            // Additional info
            $table->text('notes')->nullable();
            $table->json('documents')->nullable(); // License scans, medical certificates, etc.

            // Audit
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'authorization_status']);
            $table->index('expiry_date');
            $table->index('license_expiry_date');
        });

        Schema::table('delivery_waves', function (Blueprint $table) {
            $table->foreignUlid('approved_driver_id')
                ->nullable()
                ->constrained('hr_approved_drivers')
                ->nullOnDelete();
        });

        Schema::table('delivery_work_orders', function (Blueprint $table) {
            $table->foreign('approved_driver_id')
                ->references('id')
                ->on('hr_approved_drivers')
                ->nullOnDelete();
        });

        Schema::table('service_work_orders', function (Blueprint $table) {
            $table->foreign('approved_driver_id')
                ->references('id')
                ->on('hr_approved_drivers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_work_orders', function (Blueprint $table) {
            $table->dropForeign(['approved_driver_id']);
        });

        Schema::table('delivery_work_orders', function (Blueprint $table) {
            $table->dropForeign(['approved_driver_id']);
        });

        Schema::table('delivery_waves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_driver_id');
        });

        Schema::dropIfExists('hr_approved_drivers');
    }
};
