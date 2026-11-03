<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 60)->unique();
            $table->enum('movement_type', [
                'GRN_RECEIPT', 'SALES_DISPATCH', 'TRANSFER_IN', 'TRANSFER_OUT',
                'ADJUSTMENT_PLUS', 'ADJUSTMENT_MINUS', 'RETURN_FROM_CUSTOMER',
                'RETURN_TO_SUPPLIER', 'SCRAP', 'INITIAL_LOAD',
            ])->index();

            $table->string('source_document_type', 80)->nullable();
            $table->ulid('source_document_id')->nullable()->index();
            $table->ulid('source_line_id')->nullable();

            $table->foreignUlid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUlid('catalog_variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();
            $table->foreignUlid('stock_lot_id')->nullable()->constrained('stock_lots')->nullOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->foreignUlid('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();

            $table->foreignUlid('from_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignUlid('to_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();

            $table->decimal('unit_cost', 12, 3)->nullable();
            $table->decimal('total_cost', 14, 3)->nullable();
            $table->string('currency', 3)->default('TND');

            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();

            $table->foreignUlid('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
