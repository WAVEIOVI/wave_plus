<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_site_areas', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('site_id')
                ->constrained('client_sites')
                ->cascadeOnDelete();

            $table->foreignUlid('parent_area_id')
                ->nullable()
                ->constrained('client_site_areas')
                ->nullOnDelete();

            $table->string('code', 60)->nullable();
            $table->string('name', 255);
            $table->string('area_type', 50)->default('zone')->index();
            $table->string('classification', 50)->nullable();
            $table->string('accessibility', 50)->nullable();
            $table->integer('level_number')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->integer('sort_order')->default(0);

            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'parent_area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_site_areas');
    }
};
