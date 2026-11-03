<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_stops', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('wave_id')->nullable()->constrained('delivery_waves')->nullOnDelete();
            $table->foreignUlid('dwo_id')->constrained('delivery_work_orders')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->foreignUlid('delivery_bp_address_id')->nullable()->constrained('bp_addresses')->nullOnDelete();
            $table->dateTime('planned_arrival')->nullable();
            $table->dateTime('actual_arrival')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['wave_id', 'sequence']);
            $table->index(['dwo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_stops');
    }
};
