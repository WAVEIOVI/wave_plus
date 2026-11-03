<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_attendances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->ulid('employee_id');
            $table->date('date');
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->string('status', 30)->default('present'); // present | absent | partial
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            $table->index(['mission_id', 'date']);
            $table->unique(['mission_id', 'employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_attendances');
    }
};
