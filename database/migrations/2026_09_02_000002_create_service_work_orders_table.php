<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_work_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('swo_number', 50)->unique();
            $table->foreignUlid('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->string('fulfillment_mode', 30)->default('field_service');
            $table->foreignUlid('lsa_id')->nullable()->constrained('lsa_documents')->nullOnDelete();
            $table->foreignUlid('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignUlid('service_site_id')->nullable()->constrained('client_sites')->nullOnDelete();
            $table->foreignUlid('assigned_team_id')->nullable()->constrained('users')->nullOnDelete();
            $table->ulid('team_id')->nullable();
            $table->ulid('lead_technician_id')->nullable();
            $table->json('service_team')->nullable();
            $table->ulid('workshop_unit_id')->nullable();
            $table->foreignUlid('mobile_warehouse_id')->nullable()->constrained('warehouse_sites')->nullOnDelete();
            $table->foreignUlid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->ulid('approved_driver_id')->nullable();
            $table->string('status', 30)->default('draft');
            $table->date('scheduled_date')->nullable();
            $table->date('planned_date')->nullable();
            $table->time('planned_start_time')->nullable();
            $table->decimal('planned_duration_hours', 6, 2)->nullable();
            $table->dateTime('planned_start')->nullable();
            $table->dateTime('planned_end')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->enum('resource_validation_status', [
                'pending', 'validated', 'failed', 'override',
            ])->nullable();
            $table->json('resource_validation_errors')->nullable();
            $table->timestamp('resource_validated_at')->nullable();
            $table->foreignUlid('resource_validated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('priority', 20)->default('normal');
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignUlid('source_swo_id')->nullable()->constrained('service_work_orders')->nullOnDelete();
            $table->ulid('source_mission_id')->nullable()->index();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['fulfillment_mode', 'status']);
            $table->index(['approved_driver_id', 'status']);
            $table->index(['mobile_warehouse_id', 'status']);
        });

        Schema::create('swo_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('swo_id')->constrained('service_work_orders')->cascadeOnDelete();
            $table->foreignUlid('so_line_id')->constrained('sales_order_lines')->cascadeOnDelete();
            $table->foreignUlid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
            $table->decimal('qty_ordered', 10, 3);
            $table->decimal('qty_completed', 10, 3)->default(0);
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swo_items');
        Schema::dropIfExists('service_work_orders');
    }
};
