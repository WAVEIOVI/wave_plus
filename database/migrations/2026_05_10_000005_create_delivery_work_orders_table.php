<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_work_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('dwo_number')->unique();
            $table->foreignUlid('sales_order_id')->constrained();
            $table->foreignUlid('client_id')->constrained();
            $table->foreignUlid('warehouse_id')->nullable()->constrained('warehouse_sites')->nullOnDelete();
            $table->foreignUlid('mobile_warehouse_id')->nullable()->constrained('warehouse_sites')->nullOnDelete();
            $table->foreignUlid('delivery_site_id')->nullable()->constrained('client_sites');
            $table->foreignUlid('delivery_bp_address_id')->nullable()->constrained('bp_addresses')->nullOnDelete();
            $table->json('delivery_address_snapshot')->nullable();
            $table->foreignUlid('driver_id')->nullable()->constrained('users');
            $table->ulid('approved_driver_id')->nullable();
            $table->json('delivery_team')->nullable();
            $table->foreignUlid('vehicle_id')->nullable()->constrained();
            $table->string('status', 40)->default('draft')->index();
            $table->date('scheduled_date');
            $table->date('planned_date')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('exception_at')->nullable();
            $table->ulid('wave_id')->nullable()->index();
            $table->timestamp('eta')->nullable();
            $table->enum('priority', ['normal', 'high', 'urgent'])->default('normal');
            $table->string('fulfillment_method', 40)->nullable();
            $table->ulid('delivery_service_provider_id')->nullable();
            $table->enum('resource_validation_status', [
                'pending', 'validated', 'failed', 'override',
            ])->nullable();
            $table->json('resource_validation_errors')->nullable();
            $table->timestamp('resource_validated_at')->nullable();
            $table->foreignUlid('resource_validated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('carrier_name', 120)->nullable();
            $table->decimal('estimated_delivery_cost', 14, 3)->nullable();
            $table->decimal('actual_delivery_cost', 14, 3)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            // Standard tracking fields
            $table->foreignUlid('created_by')->nullable()->constrained('users');
            $table->foreignUlid('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['mobile_warehouse_id', 'status']);
            $table->index(['delivery_service_provider_id', 'status']);
            $table->index(['approved_driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_work_orders');
    }
};
