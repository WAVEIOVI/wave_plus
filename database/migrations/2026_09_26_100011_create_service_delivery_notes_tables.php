<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_delivery_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('sdn_number', 50)->unique();
            $table->foreignUlid('swo_id')->constrained('service_work_orders')->cascadeOnDelete();
            $table->ulid('mission_id')->nullable();
            $table->foreignUlid('client_id')->constrained('clients')->restrictOnDelete();
            $table->ulid('service_site_id')->nullable();
            $table->date('issued_date');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->foreignUlid('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->string('client_signer_name')->nullable();
            $table->text('client_signature')->nullable();
            $table->string('status', 30)->default('issued'); // issued | signed | cancelled
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('service_site_id')->references('id')->on('client_sites')->nullOnDelete();
            $table->foreign('mission_id')->references('id')->on('missions')->nullOnDelete();
            $table->index(['swo_id', 'status']);
        });

        Schema::create('service_delivery_note_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sdn_id')->constrained('service_delivery_notes')->cascadeOnDelete();
            $table->foreignUlid('intervention_id')->nullable()->constrained('interventions')->nullOnDelete();
            $table->ulid('equipment_id')->nullable();
            $table->text('description');
            $table->string('service_type', 50)->nullable();
            $table->string('result', 30)->nullable();
            $table->timestamp('documented_at')->nullable();
            $table->string('technician_name')->nullable();
            $table->timestamps();

            $table->foreign('equipment_id')->references('id')->on('client_equipment')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_delivery_note_lines');
        Schema::dropIfExists('service_delivery_notes');
    }
};
