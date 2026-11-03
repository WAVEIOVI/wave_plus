<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SWO Operational Costs — placeholder model for cost tracking.
 * Finance Center will replace this with real ledger entries later.
 * Backend remains authoritative for calculations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swo_operational_costs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('swo_id');
            $table->string('cost_type'); // labor | vehicle | consumable | workshop | external | other
            $table->string('description')->nullable();
            $table->decimal('planned_amount', 12, 3)->default(0);
            $table->decimal('actual_amount', 12, 3)->default(0);
            $table->string('currency', 3)->default('TND');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('swo_id')->references('id')->on('service_work_orders')->cascadeOnDelete();
            $table->index(['swo_id', 'cost_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swo_operational_costs');
    }
};
