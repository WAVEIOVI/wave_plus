<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_receptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reception_number', 50)->unique();
            $table->ulid('workshop_unit_id');
            $table->ulid('equipment_id');
            $table->ulid('sales_order_id')->nullable();
            $table->foreignUlid('swo_id')->nullable()->constrained('service_work_orders')->nullOnDelete();
            $table->foreignUlid('source_mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->ulid('exit_pass_id')->nullable();
            $table->foreignUlid('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignUlid('received_by')->constrained('users')->restrictOnDelete();
            $table->date('reception_date');
            $table->string('condition', 50)->default('intact'); // intact | damaged | disassembled | missing_parts
            $table->text('visible_damages')->nullable();
            $table->text('accessories_received')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('received'); // pending | received | in_diagnosis | in_progress | completed | returned
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('workshop_unit_id')->references('id')->on('workshop_units')->restrictOnDelete();
            $table->foreign('equipment_id')->references('id')->on('client_equipment')->restrictOnDelete();
            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->nullOnDelete();

            $table->index(['workshop_unit_id', 'status']);
            $table->index('reception_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_receptions');
    }
};
