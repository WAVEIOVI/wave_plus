<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Stock Lots & Serial Numbers (Traceability)
 *
 * stock_lots      → Batch / lot tracking per catalog item
 * serial_numbers  → Individual serial tracking within a lot or standalone
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Stock Lots ─────────────────────────────────────────────────
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('lot_number', 80);
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['active', 'expired', 'quarantined', 'recalled'])->default('active')->index();
            $table->text('notes')->nullable();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['catalog_item_id', 'lot_number']);
        });

        // ── Serial Numbers ─────────────────────────────────────────────
        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUlid('stock_lot_id')->nullable()->constrained('stock_lots')->nullOnDelete();
            $table->string('serial', 100)->unique();
            $table->enum('status', [
                'available', 'reserved', 'sold', 'in_repair',
                'scrapped', 'returned', 'in_transit',
            ])->default('available')->index();
            $table->foreignUlid('current_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_numbers');
        Schema::dropIfExists('stock_lots');
    }
};
