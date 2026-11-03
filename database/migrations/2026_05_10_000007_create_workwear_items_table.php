<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Assets — Workwear Items & Assignments
 *
 * Individual workwear garments (each with a QR) and their assignment history.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Workwear Items ───────────────────────────────────────
        Schema::create('workwear_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('item_code', 30)->unique();   // auto WW-YMD-X
            $table->uuid('uuid')->unique();               // QR code identifier

            $table->foreignUlid('model_id')
                ->constrained('workwear_models')
                ->cascadeOnDelete();

            $table->ulid('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();

            // ── Garment Details ─────────────────────────────────────────
            $table->string('garment_type', 80)->nullable();   // jacket, pants, vest…
            $table->string('size', 20)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('sku', 80)->nullable();

            $table->enum('status', [
                'in_stock',
                'assigned',
                'returned',
                'retired',
                'lost',
                'stolen',
            ])->default('in_stock')->index();

            $table->enum('item_condition', [
                'new',
                'good',
                'fair',
                'worn',
                'damaged',
            ])->default('new');

            // ── Stolen / Retired Data ───────────────────────────────────
            $table->boolean('is_stolen')->default(false)->index();
            $table->json('stolen_data')->nullable();    // { reported_at, report_ref, notes }
            $table->json('retired_data')->nullable();   // { retired_at, reason, notes }

            // ── Cost ────────────────────────────────────────────────────
            $table->decimal('unit_cost', 10, 3)->nullable();
            $table->foreignUlid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUlid('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('option_values')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('model_id');
            $table->index('sku');
        });

        // ── 2. Workwear Assignments ─────────────────────────────────
        Schema::create('workwear_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('item_id')
                ->constrained('workwear_items')
                ->cascadeOnDelete();

            $table->foreignUlid('employee_id')
                ->constrained('workwear_employees')
                ->cascadeOnDelete();

            $table->foreignUlid('assigned_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->dateTime('assigned_at');
            $table->dateTime('returned_at')->nullable();

            $table->string('condition_at_assign', 50)->nullable();
            $table->string('condition_at_return', 50)->nullable();
            $table->string('return_action', 50)->nullable();
            $table->foreignUlid('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('item_id');
            $table->index('employee_id');
        });

        Schema::create('workwear_item_option_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('item_id')->constrained('workwear_items')->cascadeOnDelete();
            $table->foreignUlid('option_id')->constrained('workwear_model_options')->cascadeOnDelete();
            $table->foreignUlid('value_id')->constrained('workwear_model_option_values')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['item_id', 'option_id']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workwear_item_option_values');
        Schema::dropIfExists('workwear_assignments');
        Schema::dropIfExists('workwear_items');
    }
};
