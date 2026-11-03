<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_brands', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('country_of_origin', 2)->nullable();
            $table->string('logo_source')->nullable();
            $table->string('logo_domain')->nullable();
            $table->string('logo_external_url', 2048)->nullable();
            $table->text('warranty_policy_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_brands');
    }
};
