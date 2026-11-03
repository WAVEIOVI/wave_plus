<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client Assets — Equipment Transfer Log
 *
 * Immutable history of equipment moves between sites/areas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_equipment_transfers', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('equipment_id')
                ->constrained('client_equipment')
                ->cascadeOnDelete();

            // ── From ────────────────────────────────────────────────────
            $table->foreignUlid('from_site_id')->nullable()
                ->constrained('client_sites')->nullOnDelete();
            $table->foreignUlid('from_area_id')->nullable()
                ->constrained('client_site_areas')->nullOnDelete();

            // ── To ──────────────────────────────────────────────────────
            $table->foreignUlid('to_site_id')->nullable()
                ->constrained('client_sites')->nullOnDelete();
            $table->foreignUlid('to_area_id')->nullable()
                ->constrained('client_site_areas')->nullOnDelete();

            // ── Meta ────────────────────────────────────────────────────
            $table->foreignUlid('transferred_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->dateTime('transfer_date');
            $table->string('reason', 255)->nullable();
            $table->string('condition_at_transfer', 100)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_equipment_transfers');
    }
};
