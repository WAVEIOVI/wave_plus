<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference')->unique();
            $table->foreignUlid('po_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained('suppliers')->restrictOnDelete();

            $table->foreignUlid('received_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('received_at');

            $table->string('supplier_delivery_note_ref', 100)->nullable();
            $table->string('supplier_delivery_note_path', 300)->nullable();

            $table->enum('status', ['draft', 'under_qc', 'confirmed', 'partially_rejected', 'fully_rejected', 'stocked'])->default('draft');
            $table->boolean('is_partial_delivery')->default(false);

            $table->enum('overall_qc_result', ['passed', 'passed_with_remarks', 'partially_rejected', 'fully_rejected'])->nullable();
            $table->foreignUlid('qc_performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qc_performed_at')->nullable();

            $table->boolean('delivery_on_time')->nullable();
            $table->integer('days_late')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_notes');
    }
};
