<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist templates — created in Settings / Administration.
 * SWO references a template; Mission executes it.
 * Separates template configuration from execution results.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique(); // e.g. CKL-001
            $table->json('name');             // translatable: {"en": "...", "fr": "..."}
            $table->json('description')->nullable();
            $table->string('applicable_mode')->nullable(); // field_service | workshop | null (all)
            $table->string('category')->nullable();        // pre_operation | maintenance | safety | etc.
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('checklist_template_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('template_id');
            $table->json('title');             // translatable
            $table->json('description')->nullable();
            $table->string('answer_type')->default('yes_no'); // yes_no | pass_fail | text | numeric | na
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('checklist_templates')->cascadeOnDelete();
            $table->index(['template_id', 'sort_order']);
        });

        // Add template reference to service_work_orders
        Schema::table('service_work_orders', function (Blueprint $table) {
            $table->ulid('checklist_template_id')->nullable()->after('notes');
            $table->foreign('checklist_template_id')->references('id')->on('checklist_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_work_orders', function (Blueprint $table) {
            $table->dropForeign(['checklist_template_id']);
            $table->dropColumn('checklist_template_id');
        });
        Schema::dropIfExists('checklist_template_items');
        Schema::dropIfExists('checklist_templates');
    }
};
