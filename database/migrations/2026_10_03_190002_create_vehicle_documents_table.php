<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle Documents
 *
 * Stores vehicle-related documents like registration, insurance, technical inspection, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();

            // Document info
            $table->enum('document_type', [
                'registration',
                'insurance',
                'technical_inspection',
                'rental_contract',
                'client_authorization',
                'maintenance_record',
                'purchase_invoice',
                'lease_agreement',
                'tax_certificate',
                'other',
            ]);
            $table->string('title', 200);
            $table->string('document_number', 100)->nullable();
            $table->string('issuer', 200)->nullable();

            // Dates
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('reminder_date')->nullable();

            // Status
            $table->enum('status', [
                'active',
                'expired',
                'pending_renewal',
                'archived',
            ])->default('active')->index();

            // File storage
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            // Additional info
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Audit
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'document_type']);
            $table->index(['vehicle_id', 'status']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_documents');
    }
};
