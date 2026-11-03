<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gcs_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 40)->unique();
            $table->ulid('sales_order_id');
            $table->ulid('contract_id')->nullable();
            $table->enum('status', ['generated', 'sent', 'acknowledged', 'in_progress', 'shipped', 'delivered', 'completed', 'disputed'])->default('generated');
            $table->date('committed_delivery_date');
            $table->json('goods_lines')->nullable();
            $table->json('delivery_address_snapshot')->nullable();
            $table->decimal('total_ht', 14, 3)->default(0);
            $table->decimal('total_tva', 14, 3)->default(0);
            $table->decimal('total_ttc', 14, 3)->default(0);
            $table->enum('payment_terms', ['immediate', 'net_15', 'net_30', 'net_60', 'advance_50', 'split', 'monthly', 'quarterly', 'semi_annual', 'annual', 'per_order']);
            $table->string('incoterms', 10)->nullable();
            $table->text('special_delivery_instructions')->nullable();
            $table->timestamp('client_signed_at')->nullable();
            $table->string('client_signatory')->nullable();
            $table->ulid('delivery_note_id')->nullable();
            $table->string('document_path')->nullable();
            $table->string('signed_document_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->restrictOnDelete();
            $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreign('gcs_id')->references('id')->on('gcs_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gcs_documents');
    }
};
