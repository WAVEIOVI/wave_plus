<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_shifts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 50)->unique();
            $table->json('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_duration_minutes')->default(60);
            $table->boolean('is_overnight')->default(false);
            $table->string('color', 30)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('hr_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_id');
            $table->ulid('shift_id');
            $table->date('date')->index();
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'swapped'])->default('scheduled')->index();
            $table->string('lifecycle_status', 30)->default('planned');
            $table->enum('coverage_status', ['normal', 'full_day_leave', 'partial_leave', 'absence', 'conflict'])->default('normal');
            $table->time('custom_start_time')->nullable();
            $table->time('custom_end_time')->nullable();
            $table->boolean('is_overnight_override')->default(false);
            $table->string('plan_batch_id', 36)->nullable()->index();
            $table->ulid('created_by_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('shift_id')->references('id')->on('hr_shifts')->cascadeOnDelete();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();

            $table->unique(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_schedules');
        Schema::dropIfExists('hr_shifts');
    }
};
