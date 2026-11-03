<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('return_number')->unique();
            $table->foreignUlid('dwo_id')->constrained('delivery_work_orders');
            $table->foreignUlid('client_id')->constrained();
            $table->string('return_reason'); // e.g. damaged, wrong_item
            $table->enum('status', ['draft', 'validated', 'stock_restored', 'credit_note_created'])->default('draft');
            $table->foreignUlid('credit_note_id')->nullable();
            $table->foreignUlid('stock_movement_id')->nullable()->constrained('stock_movements');
            $table->foreignUlid('validated_by')->nullable()->constrained('users');
            $table->timestamp('validated_at')->nullable();

            // Standard tracking fields
            $table->foreignUlid('created_by')->nullable()->constrained('users');
            $table->foreignUlid('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_orders');
    }
};
