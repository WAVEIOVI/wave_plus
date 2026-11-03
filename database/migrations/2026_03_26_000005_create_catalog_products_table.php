<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_products', function (Blueprint $table) {
            // PK = FK to catalog_items (1-to-1 extension)
            $table->ulid('catalog_item_id')->primary();
            $table->foreign('catalog_item_id')
                ->references('id')
                ->on('catalog_items')
                ->cascadeOnDelete();

            // ── Product Type Discriminator ────────────────────────────────
            $table->enum('product_type', ['physical', 'digital', 'service']);

            // ── Physical Product Fields ──────────────────────────────────
            $table->enum('physical_type', [
                'extinguisher',
                'wheeled_extinguisher',
                'detector',
                'detection_panel',
                'suppression_system',
                'hose_reel',
                'fire_blanket',
                'sprinkler_head',
                'alarm_device',
                'ppe',
                'cabinet',
                'other',
            ])->nullable();

            $table->decimal('capacity_value', 8, 2)->nullable();
            $table->string('capacity_unit', 10)->nullable();
            $table->string('material', 80)->nullable();
            $table->unsignedInteger('expiry_years')->nullable();

            // ── Technical Specs (available for ALL product types) ────────
            $table->json('technical_specs')->nullable();

            // ── Digital Product Fields ───────────────────────────────────
            $table->enum('digital_type', [
                'compliance_certificate',
                'compliance_report',
                'inspection_report',
                'training_material',
                'training_video',
                'software_license',
                'module_subscription',
                'other',
            ])->nullable();

            $table->enum('delivery_method', [
                'auto_generated',
                'portal_download',
                'email_attachment',
            ])->nullable();

            $table->boolean('is_downloadable')->default(false);

            // ── Generic Service Fields ──────────────────────────────────
            $table->enum('service_type', [
                'training',
                'safety_audit',
                'consultation',
                'emergency_response',
                'installation_project',
                'other',
            ])->nullable();

            $table->enum('billable_unit', [
                'per_session',
                'per_hour',
                'per_day',
                'per_action',
                'flat_fee',
            ])->nullable();

            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('max_participants')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_products');
    }
};
