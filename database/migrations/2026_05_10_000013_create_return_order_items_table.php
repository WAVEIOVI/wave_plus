<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('return_order_id')->constrained();
            $table->foreignUlid('dwo_line_id')->constrained('dwo_lines')->cascadeOnDelete();
            $table->decimal('qty_returned', 10, 3);
            $table->string('condition');
            $table->enum('stock_destination', ['return_to_stock', 'quarantine', 'scrap']);

            // Standard tracking fields
            $table->foreignUlid('created_by')->nullable()->constrained('users');
            $table->foreignUlid('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_order_items');
    }
};
