<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_skills', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 50)->unique();
            $table->json('name');
            $table->string('category', 100)->default('Technical')->index();
            $table->json('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('hr_employee_skills', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_id');
            $table->ulid('skill_id');
            $table->enum('level', ['basic', 'intermediate', 'advanced', 'expert'])->default('basic');
            $table->date('acquired_date')->nullable();
            $table->boolean('is_verified')->default(false)->index();
            $table->ulid('verified_by')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('skill_id')->references('id')->on('hr_skills')->cascadeOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['employee_id', 'skill_id']);
        });

        Schema::create('hr_certifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 50)->unique();
            $table->json('name');
            $table->json('issuing_authority')->nullable();
            $table->json('description')->nullable();
            $table->unsignedSmallInteger('validity_period_months')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('hr_employee_certifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_id');
            $table->ulid('certification_id');
            $table->string('certificate_number', 100)->nullable();
            $table->string('issuing_organization', 150)->nullable();
            $table->date('issue_date');
            $table->date('expiry_date')->nullable()->index();
            $table->enum('status', ['valid', 'expiring', 'expired', 'pending_verification'])->default('valid')->index();
            $table->ulid('verified_by')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('certification_id')->references('id')->on('hr_certifications')->cascadeOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['employee_id', 'certification_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_certifications');
        Schema::dropIfExists('hr_certifications');
        Schema::dropIfExists('hr_employee_skills');
        Schema::dropIfExists('hr_skills');
    }
};
