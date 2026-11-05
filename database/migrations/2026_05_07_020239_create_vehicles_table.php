<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('fleet_code', 50)->unique();
            $table->string('plate_number')->unique();
            $table->string('registration_number', 50)->nullable();
            $table->date('registration_date')->nullable();
            $table->string('label');
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->year('year')->nullable();
            $table->string('vin', 50)->nullable()->unique();
            $table->string('color', 50)->nullable();
            $table->string('type'); // enum or string
            $table->string('vehicle_class', 50)->nullable();
            $table->decimal('capacity_kg', 10, 2)->nullable();
            $table->decimal('capacity_m3', 8, 2)->nullable();
            $table->unsignedSmallInteger('seats')->nullable();
            $table->enum('fuel_type', [
                'petrol', 'diesel', 'electric', 'hybrid', 'lpg', 'cng', 'hydrogen', 'other',
            ])->nullable();
            $table->decimal('fuel_tank_capacity', 8, 2)->nullable();
            $table->decimal('battery_capacity_kwh', 8, 2)->nullable();
            $table->enum('ownership_type', ['company', 'rental', 'client'])->default('company');
            $table->foreignUlid('current_responsible_employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('custody_type', [
                'company_site', 'employee_home', 'mission', 'maintenance', 'rental', 'client', 'storage', 'other',
            ])->nullable();
            $table->foreignUlid('current_site_id')->nullable()->constrained('warehouse_sites')->nullOnDelete();
            $table->text('current_location_notes')->nullable();
            $table->foreignUlid('rental_provider_id')->nullable()->constrained('service_providers')->nullOnDelete();
            $table->date('rental_start_date')->nullable();
            $table->date('rental_end_date')->nullable();
            $table->string('rental_contract_reference', 100)->nullable();
            $table->decimal('rental_daily_rate', 10, 3)->nullable();
            $table->string('rental_currency', 3)->default('TND');
            $table->date('rental_return_date')->nullable();
            $table->enum('rental_status', ['active', 'pending_return', 'returned', 'extended'])->nullable();
            $table->foreignUlid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->boolean('client_supplies_driver')->default(false);
            $table->date('client_usage_start')->nullable();
            $table->date('client_usage_end')->nullable();
            $table->enum('client_vehicle_status', ['active', 'returned', 'pending_return'])->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->date('odometer_last_updated')->nullable();
            $table->date('last_inspection_date')->nullable();
            $table->date('next_inspection_date')->nullable();
            $table->date('insurance_expiry_date')->nullable();
            $table->date('registration_expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', [
                'available', 'in_use', 'maintenance', 'out_of_service',
                'rental_active', 'client_use', 'retired', 'sold',
            ])->default('available');
            $table->foreignUlid('default_driver_id')->nullable()->constrained('users');
            $table->date('last_service_date')->nullable();
            $table->date('next_service_date')->nullable();

            // Standard tracking fields
            $table->foreignUlid('created_by')->nullable()->constrained('users');
            $table->foreignUlid('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('warehouse_sites', function (Blueprint $table) {
            $table->enum('site_type', ['fixed', 'mobile'])->default('fixed')->index();
            $table->foreignUlid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->enum('mobile_status', [
                'active', 'inactive', 'in_transit', 'maintenance', 'reassignment',
            ])->nullable();
            $table->string('current_location', 200)->nullable();
            $table->text('location_notes')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();

            $table->index(['site_type', 'status']);
            $table->index(['vehicle_id', 'mobile_status']);
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_sites', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropIndex(['site_type', 'status']);
            $table->dropIndex(['vehicle_id', 'mobile_status']);
            $table->dropColumn([
                'site_type',
                'vehicle_id',
                'mobile_status',
                'current_location',
                'location_notes',
                'activated_at',
                'deactivated_at',
            ]);
        });

        Schema::dropIfExists('vehicles');
    }
};
