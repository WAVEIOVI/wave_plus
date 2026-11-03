<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_job_openings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 50)->unique();
            $table->string('title', 150);
            $table->ulid('department_id')->nullable();
            $table->ulid('position_id')->nullable();
            $table->unsignedSmallInteger('openings_count')->default(1);
            $table->enum('status', ['draft', 'published', 'closed', 'cancelled'])->default('draft')->index();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('department_id')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('position_id')->references('id')->on('hr_positions')->nullOnDelete();
        });

        Schema::create('hr_candidates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 50)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150)->index();
            $table->string('phone', 50)->nullable();
            $table->enum('status', ['applied', 'screening', 'interview', 'evaluated', 'selected', 'offer', 'hired', 'rejected'])->default('applied')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hr_job_applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('job_opening_id');
            $table->ulid('candidate_id');
            $table->date('application_date');
            $table->string('status', 50)->default('pending');
            $table->text('stage_notes')->nullable();
            $table->timestamps();

            $table->foreign('job_opening_id')->references('id')->on('hr_job_openings')->cascadeOnDelete();
            $table->foreign('candidate_id')->references('id')->on('hr_candidates')->cascadeOnDelete();
            $table->unique(['job_opening_id', 'candidate_id']);
        });

        Schema::create('hr_interviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('candidate_id');
            $table->ulid('job_opening_id')->nullable();
            $table->ulid('interviewer_id')->nullable();
            $table->dateTime('scheduled_at')->index();
            $table->unsignedSmallInteger('duration_minutes')->default(45);
            $table->text('feedback')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled')->index();
            $table->timestamps();

            $table->foreign('candidate_id')->references('id')->on('hr_candidates')->cascadeOnDelete();
            $table->foreign('job_opening_id')->references('id')->on('hr_job_openings')->nullOnDelete();
            $table->foreign('interviewer_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_interviews');
        Schema::dropIfExists('hr_job_applications');
        Schema::dropIfExists('hr_candidates');
        Schema::dropIfExists('hr_job_openings');
    }
};
