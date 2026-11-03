<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_qr_codes', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('equipment_id')
                ->constrained('client_equipment')
                ->cascadeOnDelete();

            $table->string('qr_code', 100)->unique();
            $table->boolean('is_current')->default(true)->index();
            $table->dateTime('assigned_at');
            $table->dateTime('unassigned_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->ulid('created_by')->nullable();

            $table->timestamps();

            $table->index(['equipment_id', 'is_current']);
        });

        Schema::create('equipment_intervention_cycles', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('equipment_id')
                ->constrained('client_equipment')
                ->cascadeOnDelete();

            $table->foreignUlid('source_blueprint_cycle_id')
                ->nullable()
                ->constrained('blueprint_intervention_cycles')
                ->nullOnDelete();

            $table->string('cycle_type', 50)->index();
            $table->integer('interval_value')->default(12);
            $table->string('interval_unit', 20)->default('months');
            $table->date('last_intervention_date')->nullable();
            $table->date('next_due_date')->nullable()->index();
            $table->boolean('enabled')->default(true)->index();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['equipment_id', 'enabled']);
        });

        Schema::create('equipment_location_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('equipment_id')
                ->constrained('client_equipment')
                ->cascadeOnDelete();

            $table->ulid('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();

            $table->foreignUlid('site_id')
                ->nullable()
                ->constrained('client_sites')
                ->nullOnDelete();

            $table->foreignUlid('area_id')
                ->nullable()
                ->constrained('client_site_areas')
                ->nullOnDelete();

            $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable()->index();
            $table->string('movement_type', 50)->default('initial_placement');
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->ulid('changed_by')->nullable();

            $table->timestamps();

            $table->index(['equipment_id', 'ended_at']);
        });

        Schema::create('equipment_status_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('equipment_id')
                ->constrained('client_equipment')
                ->cascadeOnDelete();

            $table->string('status', 50)->index();
            $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable()->index();
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->ulid('changed_by')->nullable();

            $table->timestamps();

            $table->index(['equipment_id', 'ended_at']);
        });

        Schema::create('equipment_option_values', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('equipment_id')
                ->constrained('client_equipment')
                ->cascadeOnDelete();

            $table->foreignUlid('blueprint_option_id')
                ->constrained('blueprint_options')
                ->cascadeOnDelete();

            $table->foreignUlid('option_value_id')
                ->nullable()
                ->constrained('blueprint_option_values')
                ->nullOnDelete();

            $table->string('option_name', 255)->nullable();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 12, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();

            $table->timestamps();

            $table->index(['equipment_id', 'blueprint_option_id']);
        });

        Schema::create('equipment_compliance_records', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('equipment_id')
                ->constrained('client_equipment')
                ->cascadeOnDelete();

            $table->string('compliance_type', 100)->index();
            $table->string('status', 50)->default('compliant')->index();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable()->index();
            $table->string('reference_number', 100)->nullable();
            $table->string('document_id', 100)->nullable();
            $table->text('notes')->nullable();

            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();

            $table->timestamps();

            $table->index(['equipment_id', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_compliance_records');
        Schema::dropIfExists('equipment_option_values');
        Schema::dropIfExists('equipment_status_histories');
        Schema::dropIfExists('equipment_location_histories');
        Schema::dropIfExists('equipment_intervention_cycles');
        Schema::dropIfExists('equipment_qr_codes');
    }
};
