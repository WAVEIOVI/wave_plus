<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workshop Exit Passes — independent traceable transfer records.
 * One mission can have multiple exit passes over its lifetime.
 * Business rules: One equipment cannot be in multiple active exit passes per mission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_exit_passes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('exit_pass_number')->unique()->nullable();
            $table->ulid('swo_id');
            $table->ulid('mission_id');
            $table->ulid('destination_workshop_unit_id')->nullable();
            $table->string('status')->default('pending'); // pending | confirmed | received | returned
            $table->date('transfer_date')->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->ulid('responsible_employee_id')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('swo_id')->references('id')->on('service_work_orders')->cascadeOnDelete();
            $table->foreign('mission_id')->references('id')->on('missions')->cascadeOnDelete();
            $table->foreign('destination_workshop_unit_id')->references('id')->on('workshop_units')->nullOnDelete();
            $table->foreign('responsible_employee_id')->references('id')->on('hr_employees')->nullOnDelete();
        });

        Schema::create('workshop_exit_pass_equipment', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('exit_pass_id');
            $table->ulid('equipment_id');
            $table->string('condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('exit_pass_id')->references('id')->on('workshop_exit_passes')->cascadeOnDelete();
            $table->foreign('equipment_id')->references('id')->on('client_equipment')->cascadeOnDelete();
            $table->unique(['exit_pass_id', 'equipment_id']);
        });

        Schema::table('workshop_receptions', function (Blueprint $table) {
            $table->foreign('exit_pass_id')->references('id')->on('workshop_exit_passes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workshop_receptions', function (Blueprint $table) {
            $table->dropForeign(['exit_pass_id']);
        });

        Schema::dropIfExists('workshop_exit_pass_equipment');
        Schema::dropIfExists('workshop_exit_passes');
    }
};
