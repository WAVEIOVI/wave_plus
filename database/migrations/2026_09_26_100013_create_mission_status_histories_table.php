<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_status_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignUlid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['mission_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_status_histories');
    }
};
