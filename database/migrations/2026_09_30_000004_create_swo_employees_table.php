<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SWO employees pivot — allows assigning individual technicians to an SWO
 * (alternative to team assignment). One employee can be designated as lead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swo_employees', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('swo_id');
            $table->ulid('employee_id');
            $table->boolean('is_lead')->default(false);
            $table->timestamps();

            $table->foreign('swo_id')->references('id')->on('service_work_orders')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->unique(['swo_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swo_employees');
    }
};
