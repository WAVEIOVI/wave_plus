<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Assets — Client Equipment
 *
 * Individual equipment units deployed at client sites, linked to Catalog blueprints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_equipment', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // ── Identity ────────────────────────────────────────────────
            $table->string('equipment_code', 30)->unique();
            $table->uuid('uuid')->unique();

            // ── Ownership & Location ────────────────────────────────────
            $table->ulid('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();

            $table->foreignUlid('site_id')->nullable()
                ->constrained('client_sites')->nullOnDelete();

            $table->foreignUlid('area_id')
                ->nullable()
                ->constrained('client_site_areas')
                ->nullOnDelete();

            // ── Blueprint & Catalog Links ───────────────────────────────
            $table->foreignUlid('blueprint_id')->nullable()
                ->constrained('equipment_blueprints')->nullOnDelete();

            $table->foreignUlid('equipment_class_id')
                ->nullable()
                ->constrained('equipment_classes')
                ->nullOnDelete();

            $table->foreignUlid('catalog_item_id')
                ->nullable()
                ->constrained('catalog_items')
                ->nullOnDelete();

            $table->foreignUlid('catalog_variant_id')
                ->nullable()
                ->constrained('catalog_variants')
                ->nullOnDelete();

            $table->foreignUlid('brand_id')
                ->nullable()
                ->constrained('catalog_brands')
                ->nullOnDelete();

            // ── Product Info ────────────────────────────────────────────
            $table->string('brand', 100)->nullable();
            $table->string('model_name', 150)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('internal_number', 100)->nullable();
            $table->string('manufacturer_part_number', 100)->nullable();
            $table->string('asset_tag', 100)->nullable();

            // ── Important Dates ─────────────────────────────────────────
            $table->date('manufacture_date')->nullable();
            $table->date('install_date')->nullable();
            $table->date('commissioning_date')->nullable();
            $table->date('decommissioning_date')->nullable();
            $table->date('warranty_start_date')->nullable();
            $table->date('warranty_end_date')->nullable();
            $table->date('last_inspection_date')->nullable();
            $table->date('next_inspection_date')->nullable();
            $table->unsignedSmallInteger('inspection_freq_months')->nullable();

            // ── Status ──────────────────────────────────────────────────
            $table->enum('status', [
                'active',
                'inactive',
                'under_maintenance',
                'decommissioned',
                'lost',
                'stolen',
            ])->default('active')->index();

            $table->enum('compliance_status', [
                'compliant',
                'non_compliant',
                'due_soon',
                'overdue',
                'not_applicable',
            ])->default('not_applicable');

            $table->string('lifecycle_status', 30)->default('operational')->index();
            $table->string('condition_status', 30)->default('good')->index();
            $table->string('criticality', 30)->default('medium')->index();

            // ── Extra Data ──────────────────────────────────────────────
            $table->json('specs')->nullable();
            $table->json('certifications')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('site_id');
            $table->index('serial_number');
            $table->index('internal_number');
            $table->index('asset_tag');
            $table->index('equipment_class_id');
            $table->index('blueprint_id');
            $table->index('area_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_equipment');
    }
};
