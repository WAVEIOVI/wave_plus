<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rfq_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('rfq_id')->constrained('rfq_headers')->cascadeOnDelete();

            // ── Catalog Item & Variant ───────────────────────────────────
            $table->foreignUlid('catalog_item_id')->nullable()->constrained('catalog_items');
            $table->foreignUlid('variant_id')->nullable()->constrained('catalog_variants')->nullOnDelete();

            // ── Ad-hoc Item Support ──────────────────────────────────────
            $table->boolean('is_ad_hoc')->default(false);
            $table->string('ad_hoc_name')->nullable();
            $table->string('ad_hoc_sku')->nullable()->default('NEW');

            // ── Line Details ─────────────────────────────────────────────
            $table->string('description_for_supplier', 300)->nullable();
            $table->decimal('qty_requested', 10, 2);
            $table->foreignUlid('unit_id')->nullable()->constrained('units_of_measure');
            $table->decimal('target_price_ht', 12, 3)->nullable();
            $table->json('required_certifications')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rfq_lines');
    }
};
