<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 40)->unique();

            // ── Versioning ───────────────────────────────────────────────
            $table->unsignedInteger('version')->default(1);
            $table->ulid('parent_contract_id')->nullable();
            $table->boolean('is_current_version')->default(true);
            $table->ulid('supersedes_contract_id')->nullable();
            $table->text('amendment_reason')->nullable();

            $table->enum('type', ['msa', 'framework']);
            $table->foreignUlid('client_id')->constrained('clients')->restrictOnDelete();

            // ── Representatives ────────────────────────────────────────
            $table->foreignUlid('provider_representative_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider_representative_name')->nullable();
            $table->string('provider_representative_title')->nullable();

            $table->foreignUlid('client_representative_id')->nullable()->constrained('bp_contacts')->nullOnDelete();
            $table->string('client_representative_name')->nullable();
            $table->string('client_representative_title')->nullable();

            $table->string('title');
            $table->string('term_model', 20)->default('fixed_term');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('auto_renewal')->default(false);
            $table->unsignedInteger('renewal_notice_days')->nullable();
            $table->unsignedInteger('renewal_duration_months')->nullable();
            $table->unsignedInteger('termination_notice_days')->nullable();
            $table->decimal('contract_value_ht', 14, 3)->nullable();
            $table->enum('payment_terms', ['immediate', 'net_15', 'net_30', 'net_60', 'advance_50', 'split', 'monthly', 'quarterly', 'semi_annual', 'annual', 'per_order']);

            // ── Commercial Terms ─────────────────────────────────────────
            $table->string('currency', 3)->default('TND');
            $table->string('payment_method')->nullable();
            $table->decimal('credit_limit', 14, 3)->nullable();
            $table->decimal('late_payment_penalty_rate', 5, 2)->nullable();
            $table->decimal('volume_commitment_value', 14, 3)->nullable();
            $table->string('commitment_type', 20)->nullable();
            $table->decimal('min_order_value', 14, 3)->nullable();

            // ── Scope ────────────────────────────────────────────────────
            $table->text('scope_description')->nullable();
            $table->json('scope_covers')->nullable();
            $table->json('authorized_requesters')->nullable();

            // ── Legal ──────────────────────────────────────────────────
            $table->string('governing_law')->default('Tunisia');
            $table->string('dispute_resolution_method')->nullable();
            $table->decimal('liability_cap_value', 14, 3)->nullable();
            $table->string('liability_cap_basis')->nullable();
            $table->boolean('liability_capped')->default(false);
            $table->boolean('confidentiality_applies')->default(false);
            $table->unsignedInteger('confidentiality_survival_years')->nullable();
            $table->boolean('exclusivity_applies')->default(false);
            $table->string('exclusivity_scope')->nullable();
            $table->text('exclusivity_notes')->nullable();

            $table->boolean('insurance_required')->default(false);
            $table->decimal('insurance_minimum_coverage', 14, 3)->nullable();
            $table->string('insurance_policy_reference')->nullable();
            $table->unsignedInteger('cure_period_days')->nullable();
            $table->unsignedInteger('negotiation_period_days')->nullable();
            $table->string('dispute_forum')->nullable();
            $table->string('arbitration_body')->nullable();

            // ── Pricing & SLA ────────────────────────────────────────────
            $table->ulid('price_list_id')->nullable();
            $table->unsignedInteger('sla_response_hours')->nullable();
            $table->unsignedInteger('standard_response_time_hours')->nullable();
            $table->text('service_quality_standard')->nullable();
            $table->unsignedInteger('sla_resolution_hours')->nullable();
            $table->decimal('sla_penalty_per_hour_ht', 12, 3)->nullable();
            $table->unsignedInteger('feasibility_review_sla_days')->nullable();
            $table->json('coverage_sites')->nullable();
            $table->json('covered_equipment_types')->nullable();

            // ── Framework-Specific ───────────────────────────────────────
            $table->decimal('volume_commitment_qty', 14, 3)->nullable();
            $table->decimal('min_call_off_qty', 14, 3)->nullable();
            $table->text('price_revision_clause')->nullable();
            $table->string('price_revision_frequency')->nullable();
            $table->boolean('price_revision_applicable')->default(false);
            $table->unsignedInteger('standard_lead_time_days')->nullable();
            $table->ulid('delivery_location_default')->nullable();
            $table->text('packaging_requirements')->nullable();
            $table->unsignedInteger('product_warranty_period_months')->nullable();
            $table->text('product_conformity_standards')->nullable();
            $table->unsignedInteger('nonconformity_return_window_days')->nullable();
            $table->json('covered_catalog_categories')->nullable();

            // ── Status & Traceability ────────────────────────────────────
            $table->enum('status', ['draft', 'negotiation', 'active', 'expiring_soon', 'renewed', 'terminated', 'expired'])->default('draft');
            $table->string('renewal_status')->nullable();
            $table->ulid('renewed_from_id')->nullable();
            $table->ulid('originating_quotation_id')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_by_client')->nullable();
            $table->string('document_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('price_list_id')->references('id')->on('price_lists')->nullOnDelete();
            $table->foreign('renewed_from_id')->references('id')->on('contracts')->nullOnDelete();
            $table->foreign('parent_contract_id')->references('id')->on('contracts')->nullOnDelete();
            $table->foreign('supersedes_contract_id')->references('id')->on('contracts')->nullOnDelete();

            $table->index('status');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
