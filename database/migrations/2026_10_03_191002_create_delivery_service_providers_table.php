<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery Service Provider Operational Profiles
 *
 * Logistics-specific operational profile referencing BP ServiceProvider master
 * Does NOT duplicate service provider identity - adds operational tracking
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_service_providers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('provider_code', 50)->unique();

            // Reference to Business Partner ServiceProvider (identity master)
            $table->foreignUlid('service_provider_id')
                ->constrained('service_providers')
                ->cascadeOnDelete();

            // Operational status
            $table->enum('operational_status', [
                'active',
                'inactive',
                'suspended',
                'under_review',
                'probation',
            ])->default('active');

            // Service capabilities
            $table->json('service_areas')->nullable(); // Cities/regions covered
            $table->json('delivery_modes')->nullable(); // standard, express, same-day, etc.
            $table->json('vehicle_types')->nullable(); // Vehicle types available
            $table->boolean('can_handle_fragile')->default(true);
            $table->boolean('can_handle_hazmat')->default(false);
            $table->boolean('can_handle_refrigerated')->default(false);
            $table->decimal('max_weight_kg', 10, 2)->nullable();
            $table->decimal('max_volume_m3', 8, 2)->nullable();

            // Contract & rates
            $table->string('contract_reference', 100)->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->enum('pricing_model', [
                'per_delivery', 'per_km', 'per_weight', 'monthly_flat', 'tiered', 'custom',
            ])->nullable();
            $table->json('rate_card')->nullable(); // Structured pricing data
            $table->string('currency', 3)->default('TND');

            // Performance tracking
            $table->unsignedInteger('total_deliveries')->default(0);
            $table->unsignedInteger('successful_deliveries')->default(0);
            $table->unsignedInteger('failed_deliveries')->default(0);
            $table->unsignedInteger('delayed_deliveries')->default(0);
            $table->decimal('success_rate', 5, 2)->nullable(); // Percentage
            $table->decimal('on_time_rate', 5, 2)->nullable(); // Percentage
            $table->decimal('average_delivery_time_hours', 8, 2)->nullable();
            $table->decimal('performance_score', 5, 2)->nullable(); // Overall score

            // SLA & capacity
            $table->json('sla_terms')->nullable();
            $table->unsignedSmallInteger('daily_capacity')->nullable();
            $table->unsignedSmallInteger('current_load')->default(0);
            $table->boolean('accepts_new_orders')->default(true);

            // Contact & operations
            $table->string('operations_contact_name', 200)->nullable();
            $table->string('operations_phone', 50)->nullable();
            $table->string('operations_email', 200)->nullable();
            $table->string('dispatch_phone', 50)->nullable();
            $table->string('emergency_phone', 50)->nullable();

            // Documentation
            $table->json('insurance_details')->nullable();
            $table->json('certifications')->nullable();
            $table->json('documents')->nullable();

            // Additional info
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Audit
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('operational_status', 'dsp_operational_status_idx');
            $table->index(['service_provider_id', 'operational_status'], 'dsp_provider_status_idx');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreign('requested_delivery_provider_id')
                ->references('id')
                ->on('delivery_service_providers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['requested_delivery_provider_id']);
        });

        Schema::dropIfExists('delivery_service_providers');
    }
};
