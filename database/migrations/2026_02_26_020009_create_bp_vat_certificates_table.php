<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bp_vat_certificates', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // ── Polymorphic Owner ────────────────────────────────────────
            $table->string('certifiable_type');
            $table->ulid('certifiable_id');
            $table->index(['certifiable_type', 'certifiable_id']);

            // ── Certificate Details ──────────────────────────────────────
            $table->string('certificate_number');
            $table->string('issuing_authority')->nullable();
            $table->date('issue_date');
            $table->date('expiration_date')->nullable()->index();

            // ── Status ───────────────────────────────────────────────────
            $table->enum('status', [
                'active',
                'expired',
                'revoked',
                'pending_renewal',
            ])->default('active')->index();
            $table->string('document_path')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // ── Composite Indexes ────────────────────────────────────────
            $table->index(['expiration_date', 'status']);
            $table->index(['certifiable_type', 'certifiable_id', 'status'], 'bp_vat_certs_owner_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bp_vat_certificates');
    }
};
