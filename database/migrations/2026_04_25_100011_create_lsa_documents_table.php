<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsa_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 40)->unique();
            $table->ulid('sales_order_id');
            $table->ulid('contract_id')->nullable();

            $table->enum('status', ['generated', 'sent', 'acknowledged', 'in_progress', 'shipped', 'delivered', 'completed', 'disputed'])->default('generated');
            $table->unsignedInteger('sla_response_hours')->nullable();
            $table->unsignedInteger('sla_resolution_hours')->nullable();
            $table->date('scheduled_start_date')->nullable();
            $table->date('scheduled_end_date')->nullable();
            $table->json('assigned_team')->nullable();
            $table->json('equipment_scope')->nullable();
            $table->json('service_scope')->nullable();
            $table->timestamp('client_signed_at')->nullable();
            $table->string('client_signatory')->nullable();
            $table->string('client_signature_path')->nullable();
            $table->string('document_path')->nullable();
            $table->string('signed_document_path')->nullable();
            $table->string('completion_report_path')->nullable();
            $table->timestamp('client_acknowledgement_at')->nullable();
            $table->text('special_terms')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->restrictOnDelete();
            $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreign('lsa_id')->references('id')->on('lsa_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsa_documents');
    }
};
