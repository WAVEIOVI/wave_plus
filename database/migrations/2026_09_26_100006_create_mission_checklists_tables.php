<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_checklists', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mission_checklist_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('checklist_id')->constrained('mission_checklists')->cascadeOnDelete();
            $table->string('title');
            $table->string('answer', 20)->default('na'); // yes | no | na
            $table->text('notes')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->string('evidence_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['checklist_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_checklist_items');
        Schema::dropIfExists('mission_checklists');
    }
};
