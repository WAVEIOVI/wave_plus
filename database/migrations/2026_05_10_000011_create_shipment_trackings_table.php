<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_trackings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('dwo_id')->constrained('delivery_work_orders');
            $table->enum('event_type', ['dispatched', 'checkpoint', 'delayed', 'arrived', 'incident']);
            $table->string('status_label');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_label')->nullable();
            $table->foreignUlid('recorded_by')->constrained('users');
            $table->timestamp('recorded_at');
            $table->text('notes')->nullable();

            // Standard tracking fields
            $table->foreignUlid('created_by')->nullable()->constrained('users');
            $table->foreignUlid('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_trackings');
    }
};
