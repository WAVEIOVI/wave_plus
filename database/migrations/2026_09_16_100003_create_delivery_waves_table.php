<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_waves', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('wave_number', 50)->unique();
            $table->date('planned_date');
            $table->time('dispatch_time')->nullable();
            $table->foreignUlid('warehouse_id')->nullable()->constrained('warehouse_sites')->nullOnDelete();
            $table->foreignUlid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignUlid('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('route_notes')->nullable();
            $table->string('status', 30)->default('draft')->index();

            // Tracking fields
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Add foreign key constraint to delivery_work_orders.wave_id now that delivery_waves exists
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('delivery_work_orders', function (Blueprint $table) {
                $table->foreign('wave_id')->references('id')->on('delivery_waves')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('delivery_work_orders', function (Blueprint $table) {
            $table->dropForeign(['wave_id']);
        });

        Schema::dropIfExists('delivery_waves');
    }
};
