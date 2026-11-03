<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('supplier_id', 12)->unique();

            // ── Identity Fields ──────────────────────────────────────────
            $table->json('legal_name');
            $table->json('commercial_name')->nullable();
            $table->string('tax_id', 50)->nullable()->unique();
            $table->string('rne_id', 50)->nullable()->unique();
            $table->enum('legal_status', [
                'sarl',
                'sa',
                'suarl',
                'snc',
                'individual',
                'public_entity',
                'association',
                'ngo',
                'other',
            ])->nullable();
            $table->enum('company_size', [
                '1-10',
                '11-50',
                '51-200',
                '201-500',
                '501-1000',
                '1001-5000',
                '5001-10000',
                '10001+',
            ])->nullable();
            $table->string('industry')->nullable()->index();
            $table->year('founding_year')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('logo_source')->nullable();
            $table->string('logo_domain')->nullable();
            $table->string('logo_external_url', 2048)->nullable();
            $table->string('primary_phone', 30)->nullable();
            $table->string('primary_email')->nullable();
            $table->json('hq_address')->nullable();
            $table->json('hq_city')->nullable();
            $table->string('hq_postcode', 20)->nullable();
            $table->json('hq_country')->nullable();

            // ── Operational Fields ───────────────────────────────────────
            $table->foreignUlid('account_manager_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('partner_since')->nullable();
            $table->decimal('satisfaction_score', 3, 1)->nullable();

            // ── Supplier-Specific Fields ─────────────────────────────────
            $table->enum('supplier_category', [
                'manufacturer',
                'distributor',
                'wholesaler',
                'general',
            ])->default('general');
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->string('payment_terms')->nullable();
            $table->decimal('minimum_order_value', 15, 3)->default(0.000);

            // ── Status & Meta ────────────────────────────────────────────
            $table->enum('status', [
                'prospect',
                'active',
                'inactive',
                'suspended',
                'blacklisted',
                'archived',
            ])->default('prospect')->index();
            $table->json('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
