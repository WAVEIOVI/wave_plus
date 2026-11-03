<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mission employees pivot — allows assigning individual technicians to a Mission.
 * Also extend missions table with planned_start/planned_end for multi-day support.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Extend missions with date range
        Schema::table('missions', function (Blueprint $table) {
            $table->dateTime('planned_start')->nullable()->after('planned_date');
            $table->dateTime('planned_end')->nullable()->after('planned_start');
        });

        // Mission employees pivot
        Schema::create('mission_employees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('mission_id');
            $table->ulid('employee_id');
            $table->boolean('is_lead')->default(false);
            $table->timestamps();

            $table->foreign('mission_id')->references('id')->on('missions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->unique(['mission_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_employees');
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn(['planned_start', 'planned_end']);
        });
    }
};
