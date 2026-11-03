<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grn_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('grn_id')
                ->constrained('goods_receipt_notes')
                ->cascadeOnDelete();

            $table->foreignUlid('po_line_id')
                ->constrained('po_lines')
                ->restrictOnDelete();

            $table->foreignUlid('catalog_item_id')
                ->nullable()
                ->constrained('catalog_items')
                ->restrictOnDelete();

            // ✅ Added here (after catalog_item_id)
            $table->foreignUlid('catalog_variant_id')
                ->nullable()
                ->constrained('catalog_variants')
                ->nullOnDelete();

            $table->boolean('is_ad_hoc')->default(false);
            $table->string('ad_hoc_name')->nullable();
            $table->string('ad_hoc_sku')->nullable();

            $table->decimal('qty_received', 10, 2);
            $table->decimal('qty_accepted', 10, 2)->default(0);
            $table->decimal('qty_rejected', 10, 2)->default(0);
            $table->decimal('unit_cost_ht', 12, 3);

            $table->string('lot_number', 80)->nullable();
            $table->date('expiry_date')->nullable();

            $table->foreignUlid('storage_location_id')
                ->nullable()
                ->constrained('warehouse_locations')
                ->nullOnDelete();

            $table->enum('qc_result', ['passed', 'passed_with_remarks', 'rejected'])->nullable();
            $table->text('qc_notes')->nullable();
            $table->json('qc_photos')->nullable();
            $table->json('certificates_received')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_lines');
    }
};
