<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bp_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // ── Polymorphic Owner ────────────────────────────────────────
            $table->string('documentable_type');
            $table->ulid('documentable_id');
            $table->index(['documentable_type', 'documentable_id']);

            // ── Users ────────────────────────────────────────────────────
            $table->foreignUlid('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUlid('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // ── Document Classification ──────────────────────────────────
            $table->enum('type', [
                'contract',
                'nda',
                'framework_agreement',
                'purchase_order',
                'compliance_certificate',
                'insurance_policy',
                'tax_id',
                'rne',
                'other',
            ])->index();
            $table->json('title');
            $table->string('reference_number')->nullable();

            // ── Dates ────────────────────────────────────────────────────
            $table->date('issue_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable()->index();
            $table->boolean('auto_renewable')->default(false);

            // ── Status ───────────────────────────────────────────────────
            $table->enum('status', [
                'draft',
                'active',
                'under_review',
                'expired',
                'terminated',
            ])->default('active')->index();
            $table->boolean('is_verified')->default(false);

            // ── File ─────────────────────────────────────────────────────
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();

            $table->json('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('source')->nullable();
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->timestamps();

            // ── Composite Indexes ────────────────────────────────────────
            $table->index(['expiration_date', 'status']);
            $table->index(['documentable_type', 'documentable_id', 'type'], 'bp_docs_owner_type_idx');
            $table->index(['documentable_type', 'documentable_id', 'status'], 'bp_docs_owner_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bp_documents');
    }
};
