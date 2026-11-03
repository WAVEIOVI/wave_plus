<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bp_addresses', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // ── Polymorphic Owner ────────────────────────────────────────
            $table->string('addressable_type');
            $table->ulid('addressable_id');

            // ── Flags ────────────────────────────────────────────────────
            $table->string('type', 50)->default('headquarters')->index();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_billing_default')->default(false);
            $table->boolean('is_shipping_default')->default(false);
            $table->json('label')->nullable();

            // ── Address Fields ───────────────────────────────────────────
            $table->json('address_street');
            $table->json('address_street2')->nullable();
            $table->json('city')->nullable();
            $table->json('state')->nullable();
            $table->string('postcode', 20)->nullable();
            $table->json('country')->nullable();

            // ── Site-Specific ────────────────────────────────────────────
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('responsible')->nullable();
            $table->string('nearest_fire_station')->nullable();
            $table->string('work_team')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->json('notes')->nullable();
            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────
            $table->index(['addressable_type', 'addressable_id'], 'bp_addr_owner_idx');
            $table->index(['addressable_type', 'addressable_id', 'is_primary'], 'bp_addr_owner_primary_idx');
            $table->index(['addressable_type', 'addressable_id', 'is_billing_default'], 'bp_addr_owner_billing_idx');
            $table->index(['addressable_type', 'addressable_id', 'is_shipping_default'], 'bp_addr_owner_shipping_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bp_addresses');
    }
};
