<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 20)->unique();      // e.g. TVA19, EXEMPT
            $table->string('name', 100);               // e.g. "TVA 19%"
            $table->string('type', 30)->default('vat'); // vat | withholding | other
            $table->decimal('rate', 8, 4)->default(0); // percentage e.g. 19.0000
            $table->boolean('is_recoverable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
