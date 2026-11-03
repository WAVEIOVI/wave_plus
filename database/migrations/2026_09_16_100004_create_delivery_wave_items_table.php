<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_wave_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('wave_id')->constrained('delivery_waves')->cascadeOnDelete();
            $table->foreignUlid('dwo_id')->constrained('delivery_work_orders')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamps();

            $table->unique(['wave_id', 'dwo_id']);
            $table->index(['wave_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_wave_items');
    }
};
