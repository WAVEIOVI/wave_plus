<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Stock Reservations (Soft Locks)
 *
 * Reservations "earmark" qty_on_hand for a specific purpose
 * (e.g. a confirmed Sales Order) without physically moving it.
 * They decrement qty_available on the matching stock_item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->foreignUlid('catalog_item_id')->constrained('catalog_items')->restrictOnDelete();
            $table->foreignUlid('catalog_variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();

            // ── Reservation source (polymorphic) ───────────────────────
            $table->string('reservable_type', 80);   // e.g. "SalesOrder", "InternalTransfer"
            $table->ulid('reservable_id');            // FK to the source document
            $table->index(['reservable_type', 'reservable_id'], 'stock_res_reservable_idx');
            $table->ulid('dwo_line_id')->nullable();
            $table->foreignUlid('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete();
            $table->index('dwo_line_id', 'stock_res_dwo_line_idx');

            $table->decimal('qty_reserved', 14, 4);
            $table->enum('status', ['active', 'fulfilled', 'cancelled', 'expired'])->default('active')->index();

            $table->timestamp('reserved_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('reason')->nullable();

            $table->foreignUlid('reserved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
