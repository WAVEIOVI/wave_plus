<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SWO locations pivot — allows one SWO to cover multiple client sites
 * (subset of the Sales Order locations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swo_locations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('swo_id');
            $table->ulid('client_site_id');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('swo_id')->references('id')->on('service_work_orders')->cascadeOnDelete();
            $table->foreign('client_site_id')->references('id')->on('client_sites')->cascadeOnDelete();
            $table->unique(['swo_id', 'client_site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swo_locations');
    }
};
