<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bp_contacts', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // ── Polymorphic Owner ────────────────────────────────────────
            $table->string('contactable_type');
            $table->ulid('contactable_id');

            // ── Identity ─────────────────────────────────────────────────
            $table->json('first_name');
            $table->json('last_name');
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('avatar_path')->nullable();

            // ── Professional ─────────────────────────────────────────────
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();

            // ── Communication ────────────────────────────────────────────
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('whatsapp_number', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('linkedin_url')->nullable();

            // ── Flags ────────────────────────────────────────────────────
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_decision_maker')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();

            $table->json('notes')->nullable();
            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────
            $table->index(['contactable_type', 'contactable_id'], 'bp_contacts_owner_idx');
            $table->index(['contactable_type', 'contactable_id', 'is_primary'], 'bp_contacts_owner_primary_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bp_contacts');
    }
};
