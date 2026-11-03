<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference')->unique();
            $table->enum('creation_path', ['from_rfq', 'from_ipr', 'manual']);
            $table->ulid('rfq_id')->nullable();
            $table->json('ipr_ids')->nullable();

            $table->foreignUlid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignUlid('supplier_contact_id')->nullable()->constrained('bp_contacts')->nullOnDelete();
            $table->ulid('site_id')->nullable();
            $table->foreignUlid('supplier_address_id')->nullable()->constrained('bp_addresses')->nullOnDelete();

            $table->string('currency', 3)->default('TND');
            $table->decimal('exchange_rate', 10, 6)->default(1.000000);
            $table->enum('payment_terms', ['immediate', 'net_15', 'net_30', 'net_60', 'net_90', 'advance_100', 'split_50_50']);
            $table->string('incoterms', 10)->nullable();
            $table->string('delivery_address', 255)->nullable();
            $table->date('committed_delivery_date')->nullable();

            $table->decimal('total_ht', 14, 3)->default(0);
            $table->decimal('total_tax', 14, 3)->default(0);
            $table->decimal('total_ttc', 14, 3)->default(0);

            $table->boolean('approval_required')->default(false);
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->enum('status', ['draft', 'pending_approval', 'approved', 'sent', 'acknowledged', 'in_delivery', 'partially_received', 'fully_received', 'closed', 'cancelled'])->default('draft');

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('supplier_po_reference', 100)->nullable();

            $table->text('notes_to_supplier')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('site_id')->references('id')->on('warehouse_sites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
