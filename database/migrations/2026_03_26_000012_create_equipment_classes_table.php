<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_classes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 60)->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->ulid('parent_id')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->integer('sort_order')->default(0);
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')
                ->references('id')
                ->on('equipment_classes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_classes');
    }
};
