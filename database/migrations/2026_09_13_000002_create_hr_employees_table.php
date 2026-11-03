<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('employee_number', 50)->unique();
            $table->ulid('user_id')->nullable()->unique();
            $table->json('first_name');
            $table->json('middle_name')->nullable();
            $table->json('last_name');
            $table->json('preferred_name')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('work_email', 150)->nullable()->index();
            $table->string('personal_email', 150)->nullable();
            $table->string('work_phone', 50)->nullable();
            $table->string('personal_phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->enum('status', ['active', 'on_leave', 'suspended', 'terminated'])->default('active')->index();
            $table->ulid('department_id')->nullable();
            $table->ulid('position_id')->nullable();
            $table->ulid('manager_id')->nullable();
            $table->ulid('current_team_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('position_id')->references('id')->on('hr_positions')->nullOnDelete();
            $table->foreign('manager_id')->references('id')->on('hr_employees')->nullOnDelete();
        });

        // Add foreign key from hr_departments.manager_id to hr_employees.id
        Schema::table('hr_departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('hr_employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::dropIfExists('hr_employees');
    }
};
