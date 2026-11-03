<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('internal_purchase_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference')->unique();
            $table->enum('trigger_source', ['auto_stock_alert', 'auto_workshop', 'manual_user', 'planned_project']);
            $table->string('trigger_reference')->nullable();
            $table->foreignUlid('requested_by')->constrained('users');
            $table->foreignUlid('assigned_to')->nullable()->constrained('users');
            $table->enum('urgency', ['low', 'normal', 'urgent', 'critical']);
            $table->date('needed_by_date')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'merged', 'converted_to_po', 'cancelled'])->default('draft');
            $table->foreignUlid('merged_into_ipr_id')->nullable()->constrained('internal_purchase_requests');
            $table->ulid('converted_to_po_id')->nullable(); // no constraint as purchase_orders table is not created yet
            $table->text('approval_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('internal_justification')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_purchase_requests');
    }
};
