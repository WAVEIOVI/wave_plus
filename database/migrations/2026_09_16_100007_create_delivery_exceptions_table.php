<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_exceptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('dwo_id')->constrained('delivery_work_orders')->cascadeOnDelete();
            $table->foreignUlid('dwo_line_id')->nullable()->constrained('dwo_lines')->nullOnDelete();
            $table->string('reason', 50)->index();
            $table->decimal('affected_qty', 10, 3)->nullable();
            $table->text('notes')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->string('status', 30)->default('open')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['dwo_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_exceptions');
    }
};
