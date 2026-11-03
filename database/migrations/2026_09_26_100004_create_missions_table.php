<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('mission_number', 50)->unique();
            $table->foreignUlid('swo_id')->constrained('service_work_orders')->cascadeOnDelete();
            $table->string('mode', 30)->default('field_service'); // field_service | workshop
            $table->string('status', 40)->default('planned');
            $table->ulid('workshop_unit_id')->nullable();
            $table->ulid('service_site_id')->nullable();
            $table->ulid('team_id')->nullable();
            $table->ulid('lead_technician_id')->nullable();
            $table->foreignUlid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->date('planned_date')->nullable();
            $table->time('planned_start_time')->nullable();
            $table->decimal('planned_duration_hours', 6, 2)->nullable();
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();
            $table->text('preparation_notes')->nullable();
            $table->string('client_contact_name')->nullable();
            $table->timestamp('client_reception_at')->nullable();
            $table->text('client_signature')->nullable();
            $table->string('client_satisfaction', 30)->nullable();
            $table->text('completion_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignUlid('source_field_mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('workshop_unit_id')->references('id')->on('workshop_units')->nullOnDelete();
            $table->foreign('service_site_id')->references('id')->on('client_sites')->nullOnDelete();
            $table->foreign('team_id')->references('id')->on('hr_work_teams')->nullOnDelete();
            $table->foreign('lead_technician_id')->references('id')->on('hr_employees')->nullOnDelete();

            $table->index(['mode', 'status']);
            $table->index(['swo_id', 'status']);
            $table->index('planned_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
