<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // ── Identification & Classification ──────────────────────────
            $table->string('code', 40)->unique();
            $table->string('barcode', 100)->nullable();
            $table->enum('item_type', ['product', 'consumable', 'eq_servicing']);
            $table->json('name');                          // i18n
            $table->json('short_description')->nullable(); // i18n

            // ── Foreign Keys ─────────────────────────────────────────────
            $table->ulid('category_id');
            $table->ulid('brand_id')->nullable();
            $table->ulid('uom_id');

            $table->foreign('category_id')->references('id')->on('catalog_categories')->restrictOnDelete();
            $table->foreign('brand_id')->references('id')->on('catalog_brands')->nullOnDelete();
            $table->foreign('uom_id')->references('id')->on('units_of_measure')->restrictOnDelete();

            // ── Commerce & Pricing ───────────────────────────────────────
            $table->boolean('is_stockable')->default(true);
            $table->boolean('is_purchasable')->default(true);
            $table->boolean('is_sellable')->default(true);

            // ── Supplier & Compliance ────────────────────────────────────
            // Media handled by Spatie Media Library (images, documents, certificates)
            $table->json('tags')->nullable();
            $table->json('certifications')->nullable();
            $table->foreignUlid('preferred_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->unsignedInteger('warranty_months')->nullable();
            $table->text('internal_notes')->nullable();

            // ── Status ───────────────────────────────────────────────────
            $table->enum('status', ['active', 'draft', 'discontinued'])->default('draft')->index();

            // ── Audit Trail ──────────────────────────────────────────────
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ──────────────────────────────────────────────────
            $table->index('item_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
