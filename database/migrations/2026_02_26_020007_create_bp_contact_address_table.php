<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bp_contact_address', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bp_contact_id')
                ->constrained('bp_contacts')
                ->cascadeOnDelete();
            $table->foreignUlid('bp_address_id')
                ->constrained('bp_addresses')
                ->cascadeOnDelete();
            $table->boolean('is_primary_location')->default(false);
            $table->timestamps();

            // ── Constraints & Indexes ────────────────────────────────────
            $table->unique(['bp_contact_id', 'bp_address_id']);
            $table->index('bp_contact_id');
            $table->index('bp_address_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bp_contact_address');
    }
};
