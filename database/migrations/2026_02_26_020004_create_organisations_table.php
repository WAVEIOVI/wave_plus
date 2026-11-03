<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('organisation_id', 12)->unique();

            // ── Identity Fields ──────────────────────────────────────────
            $table->json('legal_name');
            $table->json('commercial_name')->nullable();
            $table->string('tax_id', 50)->nullable()->unique();
            $table->string('rne_id', 50)->nullable()->unique();
            $table->string('primary_phone', 30)->nullable();
            $table->string('primary_email')->nullable();
            $table->string('logo_source')->nullable();
            $table->string('logo_domain')->nullable();
            $table->string('logo_external_url', 2048)->nullable();
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

            // ── Organisation-Specific Fields ─────────────────────────────
            $table->enum('organisation_type', [
                'government_agency',
                'municipality',
                'public_institution',
                'ngo',
                'association',
                'professional_body',
                'trade_union',
                'international_org',
                'other',
            ]);
            $table->string('registration_number')->nullable();
            $table->date('registration_date')->nullable();
            $table->string('accreditation_body')->nullable();
            $table->string('regulatory_authority')->nullable();
            $table->string('parent_organisation')->nullable();
            $table->unsignedInteger('member_count')->nullable();
            $table->enum('scope', [
                'local',
                'regional',
                'national',
                'international',
            ]);
            $table->date('partnership_start_date')->nullable();
            $table->date('partnership_end_date')->nullable();

            // ── Status & Meta ────────────────────────────────────────────
            $table->enum('status', [
                'prospect',
                'active',
                'inactive',
                'archived',
            ])->default('prospect')->index();
            $table->json('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
