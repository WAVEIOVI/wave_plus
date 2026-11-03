<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guideline_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('guideline_id')
                ->constrained('guidelines')
                ->cascadeOnDelete();

            $table->unsignedInteger('sort_order');
            $table->json('instruction');                   // i18n

            $table->enum('step_type', [
                'boolean',
                'numeric',
                'text',
                'photo',
                'choice',
            ]);

            $table->boolean('is_required')->default(true);
            $table->string('expected_value', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guideline_steps');
    }
};
