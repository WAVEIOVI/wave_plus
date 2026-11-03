<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_work_teams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 50)->unique();
            $table->json('name');
            $table->ulid('department_id')->nullable();
            $table->ulid('team_leader_id')->nullable();
            $table->string('team_type', 50)->default('operational');
            $table->boolean('is_active')->default(true)->index();
            $table->json('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('department_id')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('team_leader_id')->references('id')->on('hr_employees')->nullOnDelete();
        });

        Schema::table('hr_employees', function (Blueprint $table) {
            $table->foreign('current_team_id')->references('id')->on('hr_work_teams')->nullOnDelete();
        });

        Schema::create('hr_work_team_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('team_id');
            $table->ulid('employee_id');
            $table->boolean('is_primary')->default(true);
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->string('role_in_team', 100)->nullable();
            $table->timestamps();

            $table->foreign('team_id')->references('id')->on('hr_work_teams')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->index(['team_id', 'employee_id']);
        });

        Schema::create('hr_employment_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('employee_id');
            $table->ulid('position_id')->nullable();
            $table->ulid('department_id')->nullable();
            $table->ulid('team_id')->nullable();
            $table->ulid('manager_id')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contractor', 'intern'])->default('full_time');
            $table->enum('contract_type', ['permanent', 'fixed_term', 'temporary', 'probation'])->default('permanent');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('reason_for_change', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->foreign('position_id')->references('id')->on('hr_positions')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreign('team_id')->references('id')->on('hr_work_teams')->nullOnDelete();
            $table->foreign('manager_id')->references('id')->on('hr_employees')->nullOnDelete();
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employment_histories');
        Schema::dropIfExists('hr_work_team_members');

        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropForeign(['current_team_id']);
        });

        Schema::dropIfExists('hr_work_teams');
    }
};
