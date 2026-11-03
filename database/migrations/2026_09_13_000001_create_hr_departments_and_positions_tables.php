<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_departments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('parent_department_id')->nullable();
            $table->string('code', 50)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->ulid('manager_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_department_id')->references('id')->on('hr_departments')->nullOnDelete();
        });

        Schema::create('hr_positions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('department_id')->nullable();
            $table->string('code', 50)->unique();
            $table->json('title');
            $table->json('description')->nullable();
            $table->decimal('standard_hourly_rate', 10, 2)->nullable();
            $table->json('requirements')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('department_id')->references('id')->on('hr_departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_positions');
        Schema::dropIfExists('hr_departments');
    }
};
