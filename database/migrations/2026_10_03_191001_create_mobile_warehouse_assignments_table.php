<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile Warehouse Vehicle Assignment History
 *
 * Tracks vehicle assignment changes for mobile warehouses
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_warehouse_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('warehouse_site_id')
                ->constrained('warehouse_sites')
                ->cascadeOnDelete();
            $table->foreignUlid('vehicle_id')
                ->constrained('vehicles')
                ->restrictOnDelete();

            // Assignment period
            $table->dateTime('assigned_from');
            $table->dateTime('assigned_until')->nullable();
            $table->boolean('is_current')->default(false)->index();

            // Assignment reason
            $table->enum('assignment_reason', [
                'initial_setup',
                'vehicle_change',
                'operational_need',
                'maintenance',
                'replacement',
                'decommission',
                'other',
            ])->nullable();
            $table->text('assignment_notes')->nullable();

            // Stock transfer context
            $table->boolean('stock_transferred')->default(false);
            $table->dateTime('stock_transfer_completed_at')->nullable();
            $table->unsignedInteger('items_transferred')->nullable();

            // Audit
            $table->foreignUlid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_site_id', 'is_current']);
            $table->index(['vehicle_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_warehouse_assignments');
    }
};
