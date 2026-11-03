<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_locations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->ulid('bp_address_id');
            $table->ulid('client_site_id')->nullable();
            $table->string('purpose', 30)->default('delivery');
            $table->boolean('is_primary')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('bp_address_id')->references('id')->on('bp_addresses')->restrictOnDelete();
            $table->foreign('client_site_id')->references('id')->on('client_sites')->nullOnDelete();

            $table->index('sales_order_id');
            $table->index('bp_address_id');
            $table->index(['sales_order_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_locations');
    }
};
