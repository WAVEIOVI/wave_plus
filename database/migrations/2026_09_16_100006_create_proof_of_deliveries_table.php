<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proof_of_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('dwo_id')->unique()->constrained('delivery_work_orders')->cascadeOnDelete();
            $table->timestamp('delivered_at');
            $table->string('receiver_name', 150);
            $table->string('receiver_title', 100)->nullable();
            $table->longText('signature_data')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('condition_overall', 30)->default('good');
            $table->text('notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proof_of_deliveries');
    }
};
