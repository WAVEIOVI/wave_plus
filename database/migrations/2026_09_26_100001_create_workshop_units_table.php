<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 50)->unique();
            $table->json('name'); // Spatie translatable
            $table->json('description')->nullable(); // Spatie translatable
            $table->string('type', 30)->default('fixed'); // fixed | mobile
            $table->string('status', 30)->default('active'); // active | inactive | maintenance
            $table->string('location')->nullable();
            $table->foreignUlid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->text('capacity_notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreign('workshop_unit_id')->references('id')->on('workshop_units')->nullOnDelete();
        });

        Schema::table('service_work_orders', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('hr_work_teams')->nullOnDelete();
            $table->foreign('lead_technician_id')->references('id')->on('hr_employees')->nullOnDelete();
            $table->foreign('workshop_unit_id')->references('id')->on('workshop_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_work_orders', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['lead_technician_id']);
            $table->dropForeign(['workshop_unit_id']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['workshop_unit_id']);
        });

        Schema::dropIfExists('workshop_units');
    }
};
