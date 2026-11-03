<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_providers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('service_provider_id', 12)->unique();

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

            // ── ServiceProvider-Specific Fields ──────────────────────────
            $table->enum('provider_tier', [
                'standard',
                'preferred',
                'certified_partner',
            ])->default('standard');
            $table->enum('availability_status', [
                'available',
                'partially_available',
                'fully_booked',
                'inactive',
            ])->default('available');
            $table->enum('contract_type', [
                'fixed_price',
                'time_and_materials',
                'retainer',
                'per_mission',
            ])->nullable();
            $table->decimal('base_rate', 10, 3)->nullable();
            $table->string('rate_currency', 10)->default('TND');
            $table->json('specialties')->nullable();
            $table->json('service_zones')->nullable();
            $table->json('certifications')->nullable();

            // ── Status & Meta ────────────────────────────────────────────
            $table->enum('status', [
                'prospect',
                'active',
                'inactive',
                'suspended',
                'blacklisted',
                'archived',
            ])->default('prospect')->index();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_providers');
    }
};
