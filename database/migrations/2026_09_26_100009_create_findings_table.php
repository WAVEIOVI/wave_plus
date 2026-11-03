<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulidMorphs('findable');
            $table->ulid('equipment_id')->nullable();
            $table->ulid('site_id')->nullable();
            $table->string('type', 30)->default('observation'); // conformity | non_conformity | observation | recommendation
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity', 30)->default('low'); // low | medium | high | critical
            $table->text('recommendation')->nullable();
            $table->text('corrective_action')->nullable();
            $table->boolean('requires_workshop_transfer')->default(false);
            $table->string('status', 30)->default('open'); // open | in_progress | resolved | dismissed
            $table->unsignedInteger('assigned_to')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'severity']);
            $table->index('status');
            $table->foreign('equipment_id')->references('id')->on('client_equipment')->nullOnDelete();
            $table->foreign('site_id')->references('id')->on('client_sites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
