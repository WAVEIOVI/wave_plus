<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Warehouse Spatial Hierarchy
 *
 * warehouse_sites   → Physical buildings / campuses
 * warehouse_zones   → Logical areas within a site (e.g. Cold-Store, Rack-A)
 * warehouse_locations → Individual bins / shelves / bays
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Sites ────────────────────────────────────────────────────
        Schema::create('warehouse_sites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 20)->unique();           // e.g. "WH-MAIN"
            $table->json('name');                            // i18n { en, fr, ar }
            $table->json('address')->nullable();             // structured address blob
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();        // ISO 3166-1 alpha-3 or full name
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->foreignUlid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'inactive', 'maintenance', 'archived'])->default('active')->index();
            $table->text('notes')->nullable();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── 2. Zones ───────────────────────────────────────────────────
        Schema::create('warehouse_zones', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->constrained('warehouse_sites')->cascadeOnDelete();
            $table->string('code', 20);                     // e.g. "ZONE-A"
            $table->json('name');                            // i18n
            $table->enum('zone_type', [
                'receiving', 'storage', 'picking', 'packing',
                'shipping', 'staging', 'quarantine', 'returns', 'cold_storage',
            ])->default('storage');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['site_id', 'code']);
        });

        // ── 3. Locations (bins / shelves) ──────────────────────────────
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('zone_id')->constrained('warehouse_zones')->cascadeOnDelete();
            $table->string('barcode', 80)->unique();        // scannable label
            $table->string('aisle', 10)->nullable();
            $table->string('rack', 10)->nullable();
            $table->string('shelf', 10)->nullable();
            $table->string('bin', 10)->nullable();
            $table->enum('location_type', [
                'shelf', 'bin', 'floor', 'pallet', 'rack', 'bulk', 'pick',
                'overstock', 'staging', 'quarantine', 'returns', 'cross_dock',
                'cold_storage', 'hazmat',
            ])->default('shelf');
            $table->decimal('max_weight_kg', 10, 2)->nullable();
            $table->decimal('max_volume_m3', 10, 4)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('warehouse_zones');
        Schema::dropIfExists('warehouse_sites');
    }
};
