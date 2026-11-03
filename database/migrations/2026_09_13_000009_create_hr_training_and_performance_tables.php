<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_training_programs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 50)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('category', 100)->default('General')->index();
            $table->json('provider')->nullable();
            $table->decimal('duration_hours', 5, 2)->default(8.00);
            $table->ulid('target_skill_id')->nullable();
            $table->ulid('target_certification_id')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('target_skill_id')->references('id')->on('hr_skills')->nullOnDelete();
            $table->foreign('target_certification_id')->references('id')->on('hr_certifications')->nullOnDelete();
        });

        Schema::create('hr_training_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('training_program_id');
            $table->string('session_code', 50)->unique();
            $table->json('trainer')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('location', 150)->nullable();
            $table->unsignedSmallInteger('max_participants')->default(20);
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled')->index();
            $table->timestamps();

            $table->foreign('training_program_id')->references('id')->on('hr_training_programs')->cascadeOnDelete();
        });

        Schema::create('hr_employee_trainings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('training_session_id');
            $table->ulid('employee_id');
            $table->enum('status', ['enrolled', 'attended', 'completed', 'failed', 'cancelled'])->default('enrolled')->index();
            $table->decimal('score', 5, 2)->nullable();
            $table->date('completion_date')->nullable();
            $table->string('certificate_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('training_session_id')->references('id')->on('hr_training_sessions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->unique(['training_session_id', 'employee_id']);
        });

        Schema::create('hr_appraisal_cycles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 150);
            $table->enum('cycle_type', ['annual', 'quarterly', 'probation', 'project'])->default('annual');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'active', 'review', 'completed'])->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('hr_employee_appraisals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('appraisal_cycle_id');
            $table->ulid('employee_id');
            $table->ulid('reviewer_id')->nullable();
            $table->decimal('overall_score', 4, 2)->nullable();
            $table->enum('status', ['draft', 'submitted', 'reviewed', 'acknowledged'])->default('draft')->index();
            $table->text('manager_feedback')->nullable();
            $table->text('employee_feedback')->nullable();
            $table->json('goals')->nullable();
            $table->json('ratings')->nullable();
            $table->timestamps();

            $table->foreign('appraisal_cycle_id')->references('id')->on('hr_appraisal_cycles')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('reviewer_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['appraisal_cycle_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_appraisals');
        Schema::dropIfExists('hr_appraisal_cycles');
        Schema::dropIfExists('hr_employee_trainings');
        Schema::dropIfExists('hr_training_sessions');
        Schema::dropIfExists('hr_training_programs');
    }
};
