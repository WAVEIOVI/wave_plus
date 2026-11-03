<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_forecasts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->foreignUlid('client_id')->constrained('clients')->restrictOnDelete();
            $table->ulid('equipment_id')->nullable();
            $table->ulid('service_site_id')->nullable();
            $table->string('forecast_type', 50)->default('periodic_maintenance');
            $table->date('expected_date');
            $table->decimal('estimated_hours', 6, 2)->nullable();
            $table->unsignedInteger('estimated_technicians')->default(1);
            $table->string('priority', 20)->default('normal');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft'); // draft | confirmed | converted | dismissed
            $table->foreignUlid('converted_swo_id')->nullable()->constrained('service_work_orders')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('equipment_id')->references('id')->on('client_equipment')->nullOnDelete();
            $table->foreign('service_site_id')->references('id')->on('client_sites')->nullOnDelete();

            $table->index(['status', 'expected_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_forecasts');
    }
};
