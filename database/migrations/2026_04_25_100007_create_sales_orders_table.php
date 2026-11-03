<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference', 40)->unique();
            $table->string('so_type', 20)->default('sales');
            $table->string('creation_path', 50)->default('manual');
            $table->ulid('quotation_id')->nullable();
            $table->ulid('contract_id')->nullable();
            $table->foreignUlid('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('client_po_reference', 100)->nullable();
            $table->string('client_po_document_path')->nullable();
            $table->string('requested_fulfillment_mode', 40)->nullable();
            $table->ulid('workshop_unit_id')->nullable();
            $table->enum('service_frequency', [
                'one_time',
                'daily',
                'weekly',
                'bi_weekly',
                'monthly',
                'quarterly',
                'semi_annually',
                'annually',
                'custom',
            ])->nullable();
            $table->unsignedInteger('service_frequency_custom_value')->nullable();
            $table->string('service_frequency_custom_unit', 30)->nullable();
            $table->unsignedInteger('planned_mission_count')->nullable();
            $table->ulid('requested_delivery_provider_id')->nullable();
            $table->string('requested_vehicle_type', 100)->nullable();
            $table->json('delivery_requirements')->nullable();
            $table->string('preferred_service_mode', 50)->nullable();
            $table->string('preferred_vehicle_type', 100)->nullable();
            $table->boolean('requires_mobile_workshop')->default(false);
            $table->boolean('requires_mobile_warehouse')->default(false);
            $table->json('service_requirements')->nullable();
            $table->date('requested_delivery_date_from')->nullable();
            $table->date('requested_delivery_date_to')->nullable();
            $table->date('committed_delivery_date_from')->nullable();
            $table->date('committed_delivery_date_to')->nullable();
            $table->string('status', 50)->default('draft');
            $table->string('currency', 3)->default('TND');
            $table->enum('payment_terms', ['immediate', 'net_15', 'net_30', 'net_60', 'advance_50', 'split', 'monthly', 'quarterly', 'semi_annual', 'annual', 'per_order']);
            $table->decimal('total_ht', 14, 3)->default(0);
            $table->decimal('total_tva', 14, 3)->default(0);
            $table->decimal('total_ttc', 14, 3)->default(0);
            $table->boolean('has_service_lines')->default(false);
            $table->boolean('has_goods_lines')->default(false);
            $table->ulid('lsa_id')->nullable();
            $table->ulid('gcs_id')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->text('client_notes')->nullable();
            $table->foreignUlid('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
            $table->timestamp('pricing_locked_at')->nullable();
            $table->string('feasibility_status', 20)->default('pending');
            $table->string('credit_check_result', 30)->nullable();
            $table->string('stock_check_result', 30)->nullable();
            $table->string('capacity_check_result', 30)->nullable();
            $table->boolean('requires_secondary_approval')->default(false);
            $table->text('rejection_reason')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUlid('parent_so_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->boolean('is_current_version')->default(true);
            $table->timestamp('starter_locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();

            $table->index('requested_delivery_provider_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
