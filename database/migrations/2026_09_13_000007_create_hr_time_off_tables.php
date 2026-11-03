<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 50)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->boolean('is_paid')->default(true);
            $table->decimal('max_days_per_year', 5, 2)->default(30.00);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('allows_hourly')->default(false);
            $table->unsignedSmallInteger('min_hourly_unit_minutes')->default(15);
            $table->decimal('max_hours_per_request', 4, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('hr_leave_policies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('leave_type_id');
            $table->ulid('department_id')->nullable();
            $table->decimal('accrual_rate_per_month', 4, 2)->default(1.75);
            $table->decimal('max_carryover_days', 5, 2)->default(5.00);
            $table->timestamps();

            $table->foreign('leave_type_id')->references('id')->on('hr_leave_types')->cascadeOnDelete();
            $table->foreign('department_id')->references('id')->on('hr_departments')->nullOnDelete();
        });

        Schema::create('hr_leave_balances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_id');
            $table->ulid('leave_type_id');
            $table->unsignedSmallInteger('year')->index();
            $table->decimal('accrued_days', 5, 2)->default(0);
            $table->decimal('approved_used_days', 5, 2)->default(0);
            $table->decimal('pending_reserved_days', 5, 2)->default(0);
            $table->decimal('available_days', 5, 2)->default(0);
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('leave_type_id')->references('id')->on('hr_leave_types')->cascadeOnDelete();
            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('hr_leave_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 50)->unique();
            $table->ulid('employee_id');
            $table->ulid('leave_type_id');
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->enum('duration_type', ['full_day', 'partial_day', 'hourly'])->default('full_day');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('total_days', 5, 2);
            $table->text('reason')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'cancelled', 'taken'])->default('draft')->index();
            $table->ulid('approver_id')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->ulid('requested_by_id')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('leave_type_id')->references('id')->on('hr_leave_types')->cascadeOnDelete();
            $table->foreign('approver_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('requested_by_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('hr_public_holidays', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->json('name');
            $table->date('date')->index();
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('year')->index();
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_half_day')->default(false);
            $table->boolean('applies_to_all')->default(true);
            $table->foreignUlid('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->string('country_code', 5)->default('TN')->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->foreign('covering_leave_id')->references('id')->on('hr_leave_requests')->nullOnDelete();
        });

        Schema::table('hr_attendance_exceptions', function (Blueprint $table) {
            $table->foreign('covering_leave_request_id')->references('id')->on('hr_leave_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendance_exceptions', function (Blueprint $table) {
            $table->dropForeign(['covering_leave_request_id']);
        });

        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->dropForeign(['covering_leave_id']);
        });

        Schema::dropIfExists('hr_public_holidays');
        Schema::dropIfExists('hr_leave_requests');
        Schema::dropIfExists('hr_leave_balances');
        Schema::dropIfExists('hr_leave_policies');
        Schema::dropIfExists('hr_leave_types');
    }
};
