<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle Fuel/Energy Consumption
 *
 * Tracks fuel and energy consumption for fleet vehicles
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_fuel_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();

            // Transaction details
            $table->date('fill_date');
            $table->time('fill_time')->nullable();
            $table->enum('fuel_type', [
                'petrol',
                'diesel',
                'electric',
                'hybrid',
                'lpg',
                'cng',
                'hydrogen',
                'other',
            ]);

            // Quantity
            $table->decimal('quantity', 10, 3); // liters or kWh
            $table->string('unit', 10)->default('L'); // L for liters, kWh for electric

            // Odometer
            $table->unsignedInteger('odometer_km')->nullable();

            // Cost
            $table->decimal('unit_price', 10, 3)->nullable();
            $table->decimal('total_cost', 10, 3)->nullable();
            $table->string('currency', 3)->default('TND');

            // Location
            $table->string('supplier', 200)->nullable();
            $table->string('station', 200)->nullable();
            $table->string('city', 100)->nullable();

            // Reference
            $table->string('receipt_number', 100)->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->enum('payment_method', [
                'fleet_card', 'cash', 'company_card', 'employee_reimbursement', 'other',
            ])->nullable();

            // Context
            $table->foreignUlid('filled_by_employee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUlid('driver_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_full_tank')->default(false);
            $table->text('notes')->nullable();

            // Audit
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'fill_date']);
            $table->index('fill_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_fuel_records');
    }
};
