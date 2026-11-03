<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_time_clock_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_id');
            $table->enum('event_type', ['in', 'out'])->index();
            $table->dateTime('event_time')->index();
            $table->enum('source', ['web', 'mobile', 'manual', 'qr', 'pin', 'device', 'biometric'])->default('web');
            $table->string('ip_address', 45)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('device_id', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->index(['employee_id', 'event_time']);
        });

        Schema::create('hr_attendances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_id');
            $table->date('date')->index();
            $table->time('scheduled_in')->nullable();
            $table->time('scheduled_out')->nullable();
            $table->time('actual_in')->nullable();
            $table->time('actual_out')->nullable();
            $table->decimal('scheduled_hours', 4, 2)->default(0);
            $table->decimal('worked_hours', 4, 2)->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_departure_minutes')->default(0);
            $table->decimal('overtime_hours', 4, 2)->default(0);
            $table->enum('status', ['present', 'late', 'absent', 'partial', 'on_leave', 'holiday'])->default('absent')->index();
            $table->enum('coverage_status', ['normal', 'full_day_leave', 'partial_leave', 'absence', 'conflict'])->default('normal');
            $table->ulid('schedule_id')->nullable();
            $table->ulid('covering_leave_id')->nullable();
            $table->boolean('is_corrected')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('schedule_id')->references('id')->on('hr_schedules')->nullOnDelete();
            $table->unique(['employee_id', 'date']);
        });

        Schema::create('hr_attendance_corrections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('attendance_id');
            $table->ulid('requester_id');
            $table->time('old_actual_in')->nullable();
            $table->time('old_actual_out')->nullable();
            $table->time('requested_actual_in')->nullable();
            $table->time('requested_actual_out')->nullable();
            $table->text('reason');
            $table->ulid('approver_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->text('decision_reason')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('attendance_id')->references('id')->on('hr_attendances')->cascadeOnDelete();
            $table->foreign('requester_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('approver_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('hr_attendance_exceptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_id');
            $table->date('date')->index();
            $table->ulid('time_clock_event_id')->nullable();
            $table->ulid('attendance_id')->nullable();
            $table->enum('exception_type', [
                'early_arrival',
                'late_arrival',
                'early_departure',
                'overtime',
                'missing_checkout',
                'missing_checkin',
                'invalid_event_sequence',
                'leave_mismatch',
                'schedule_mismatch',
                'other',
            ])->index();
            $table->time('expected_time')->nullable();
            $table->time('actual_time')->nullable();
            $table->unsignedSmallInteger('deviation_minutes')->default(0);
            $table->ulid('covering_leave_request_id')->nullable();
            $table->enum('review_status', [
                'open',
                'acknowledged',
                'resolved',
                'dismissed',
            ])->default('open')->index();
            $table->text('notes')->nullable();
            $table->ulid('reviewed_by_id')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('time_clock_event_id')->references('id')->on('hr_time_clock_events')->nullOnDelete();
            $table->foreign('attendance_id')->references('id')->on('hr_attendances')->nullOnDelete();
            $table->foreign('reviewed_by_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['employee_id', 'date']);
            $table->index(['review_status', 'date']);
        });

        Schema::create('hr_attendance_config', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('scope', 30)->default('global')->index();
            $table->string('scope_id', 36)->nullable();
            $table->unsignedSmallInteger('early_checkin_grace_minutes')->default(15);
            $table->unsignedSmallInteger('late_checkin_grace_minutes')->default(15);
            $table->unsignedSmallInteger('early_checkout_grace_minutes')->default(15);
            $table->unsignedSmallInteger('late_checkout_grace_minutes')->default(15);
            $table->unsignedSmallInteger('hourly_leave_tolerance_minutes')->default(15);
            $table->unsignedSmallInteger('min_hourly_leave_unit_minutes')->default(15);
            $table->timestamps();

            $table->unique(['scope', 'scope_id']);
        });

        DB::table('hr_attendance_config')->insert([
            'id' => (string) Str::ulid(),
            'scope' => 'global',
            'scope_id' => null,
            'early_checkin_grace_minutes' => 15,
            'late_checkin_grace_minutes' => 15,
            'early_checkout_grace_minutes' => 15,
            'late_checkout_grace_minutes' => 15,
            'hourly_leave_tolerance_minutes' => 15,
            'min_hourly_leave_unit_minutes' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_config');
        Schema::dropIfExists('hr_attendance_exceptions');
        Schema::dropIfExists('hr_attendance_corrections');
        Schema::dropIfExists('hr_attendances');
        Schema::dropIfExists('hr_time_clock_events');
    }
};
