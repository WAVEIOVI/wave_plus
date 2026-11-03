<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dwo_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('dwo_id')->constrained('delivery_work_orders');
            $table->foreignUlid('so_item_id')->constrained('sales_order_lines');
            $table->foreignUlid('catalog_item_id')->constrained();
            $table->foreignUlid('catalog_variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();
            $table->foreignUlid('commercial_catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->foreignUlid('commercial_catalog_variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();
            $table->string('substitution_status', 40)->nullable();
            $table->text('substitution_reason')->nullable();
            $table->foreignUlid('substitution_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('substitution_approved_at')->nullable();
            $table->foreignUlid('proposed_catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->foreignUlid('proposed_catalog_variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();
            $table->text('description_snapshot')->nullable();
            $table->string('variant_label_snapshot', 255)->nullable();
            $table->string('uom_code_snapshot', 20)->nullable();
            $table->decimal('qty_ordered', 10, 3);
            $table->decimal('qty_allocated', 10, 3)->default(0);
            $table->decimal('qty_reserved', 10, 3)->default(0);
            $table->decimal('qty_shortage', 10, 3)->default(0);
            $table->decimal('qty_picked', 10, 3)->nullable();
            $table->decimal('qty_loaded', 10, 3)->default(0);
            $table->decimal('qty_delivered', 10, 3)->nullable();
            $table->decimal('qty_returned', 10, 3)->default(0);
            $table->string('lot_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('warehouse_location')->nullable();
            $table->foreignUlid('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('item_status', 40)->default('pending');
            $table->enum('condition_on_delivery', ['good', 'damaged', 'missing'])->nullable();
            $table->text('driver_notes')->nullable();

            // Standard tracking fields
            $table->foreignUlid('created_by')->nullable()->constrained('users');
            $table->foreignUlid('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('item_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dwo_lines');
    }
};
