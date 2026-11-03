<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Assets — Workwear Employees
 *
 * Employee roster per client for workwear assignment tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workwear_employees', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();

            $table->string('employee_ref', 50)->nullable();  // client's internal ref
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('role', 100)->nullable();
            $table->string('position', 100)->nullable();

            $table->enum('status', [
                'active',
                'inactive',
                'terminated',
            ])->default('active')->index();

            // ── Sizes ───────────────────────────────────────────────────
            $table->json('sizes')->nullable();  // e.g. {"jacket":"L","pants":"M","shoes":"42","gloves":"XL"}
            $table->json('workwear_requirements')->nullable();
            $table->json('measurements')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
        });

        Schema::create('workwear_employee_models', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('employee_id')->constrained('workwear_employees')->cascadeOnDelete();
            $table->foreignUlid('model_id')->constrained('workwear_models')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('size', 20)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('assigned_at');
            $table->dateTime('ended_at')->nullable();
            $table->foreignUlid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'ended_at']);
            $table->index('model_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workwear_employee_models');
        Schema::dropIfExists('workwear_employees');
    }
};
