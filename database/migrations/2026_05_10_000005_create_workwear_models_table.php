<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Assets — Workwear Models & Model Versions
 *
 * Defines the catalog of workwear designs (garment templates).
 * Versions track snapshot history of specification changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workwear_fabrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('composition', 255)->nullable();
            $table->unsignedSmallInteger('weight_gsm')->nullable();
            $table->string('weave_type', 100)->nullable();
            $table->json('properties')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        // ── 1. Workwear Models ──────────────────────────────────────
        Schema::create('workwear_models', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreignUlid('catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();

            $table->string('model_ref', 40)->unique();  // auto WM-CLI-YM-X
            $table->string('name', 255);

            // ── Garment Specification ───────────────────────────────────
            $table->json('garment_types')->nullable();   // e.g. ["jacket","pants","vest"]
            $table->string('fabric', 150)->nullable();
            $table->foreignUlid('fabric_id')->nullable()->constrained('workwear_fabrics')->nullOnDelete();
            $table->json('colors')->nullable();          // e.g. ["navy","orange"]
            $table->json('size_range')->nullable();
            $table->string('custom_type', 100)->nullable();
            $table->string('silhouette', 40)->nullable();
            $table->string('silhouette_category', 100)->nullable();
            $table->string('silhouette_subcategory', 100)->nullable();
            $table->string('silhouette_item', 150)->nullable();
            $table->text('notes')->nullable();

            // ── Printing / Branding ─────────────────────────────────────
            $table->json('print_text')->nullable();      // e.g. {"front":"LOGO","back":"Company Name"}
            $table->string('print_type', 80)->nullable();
            $table->string('logo_placement', 100)->nullable();
            $table->string('logo_ged_ref', 255)->nullable();

            // ── Safety Standards ─────────────────────────────────────────
            $table->string('en_standards', 255)->nullable();  // e.g. "EN ISO 11612, EN 1149-5"
            $table->string('fr_rating', 100)->nullable();
            $table->string('waterproofing', 100)->nullable();
            $table->json('technical_specs')->nullable();
            $table->json('certifications')->nullable();
            $table->json('standards')->nullable();
            $table->json('custom_made_requirements')->nullable();

            // ── Supply ──────────────────────────────────────────────────
            $table->ulid('supplier_id')->nullable();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();

            $table->enum('status', [
                'draft',
                'active',
                'discontinued',
            ])->default('draft')->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('catalog_item_id');
            $table->index('fabric_id');
        });

        // ── 2. Workwear Model Versions (snapshot history) ───────────
        Schema::create('workwear_model_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workwear_model_id')
                ->constrained('workwear_models')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('version_number');
            $table->json('snapshot');   // full serialised copy of the model at that time
            $table->string('change_summary', 500)->nullable();

            $table->foreignUlid('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['workwear_model_id', 'version_number']);
        });

        Schema::create('workwear_model_placements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workwear_model_id')->constrained('workwear_models')->cascadeOnDelete();
            $table->string('view', 10)->default('front');
            $table->string('type', 30)->default('logo');
            $table->string('label', 120);
            $table->string('zone', 40)->default('chest_left');
            $table->string('text', 255)->nullable();
            $table->string('method', 80)->nullable();
            $table->string('color', 30)->nullable();
            $table->decimal('width_mm', 7, 1)->nullable();
            $table->decimal('height_mm', 7, 1)->nullable();
            $table->decimal('offset_x_mm', 7, 1)->default(0);
            $table->decimal('offset_y_mm', 7, 1)->default(0);
            $table->ulid('media_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['workwear_model_id', 'view']);
        });

        Schema::create('workwear_model_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workwear_model_id')->constrained('workwear_models')->cascadeOnDelete();
            $table->string('name', 150);
            $table->integer('sort_order')->default(0);
            $table->string('ui_control', 40)->default('select');
            $table->timestamps();

            $table->index('workwear_model_id');
        });

        Schema::create('workwear_model_option_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('option_id')->constrained('workwear_model_options')->cascadeOnDelete();
            $table->string('value', 150);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workwear_model_option_values');
        Schema::dropIfExists('workwear_model_options');
        Schema::dropIfExists('workwear_model_placements');
        Schema::dropIfExists('workwear_model_versions');
        Schema::dropIfExists('workwear_models');
        Schema::dropIfExists('workwear_fabrics');
    }
};
