<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Assets — Client Sites
 *
 * Physical locations belonging to a client where equipment/assets are deployed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_sites', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // ── Identity ────────────────────────────────────────────────
            $table->ulid('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();

            $table->ulid('bp_address_id')->nullable();
            $table->foreign('bp_address_id')->references('id')->on('bp_addresses')->nullOnDelete();

            $table->string('site_code', 30)->unique();   // auto CS-YMD-X
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('operating_hours', 255)->nullable();
            $table->text('access_instructions')->nullable();
            $table->text('safety_instructions')->nullable();
            $table->text('special_instructions')->nullable();
            $table->date('opened_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();

            $table->string('site_type', 50)->default('office')->index();

            $table->enum('status', [
                'active',
                'inactive',
                'under_construction',
                'decommissioned',
            ])->default('active')->index();

            $table->string('risk_class', 50)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('bp_address_id');
        });

        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->foreign('target_site_id')->references('id')->on('client_sites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropForeign(['target_site_id']);
        });

        Schema::dropIfExists('client_sites');
    }
};
