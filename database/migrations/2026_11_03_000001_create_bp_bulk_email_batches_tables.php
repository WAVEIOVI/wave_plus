<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bp_bulk_email_batches')) {
            Schema::create('bp_bulk_email_batches', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('partner_type', 30)->index();
                $table->unsignedBigInteger('sender_profile_id');
                $table->unsignedBigInteger('template_id')->nullable();
                $table->string('language', 10)->default('fr');
                $table->string('recipient_rule', 50)->default('primary_email_first');
                $table->boolean('include_pdf')->default(true);
                $table->unsignedInteger('total_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->string('status', 30)->default('pending')->index();
                $table->string('idempotency_key', 100)->nullable()->unique();
                $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('sender_profile_id')
                    ->references('id')->on('email_sender_profiles')
                    ->cascadeOnDelete();
                $table->foreign('template_id')
                    ->references('id')->on('email_templates')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('bp_bulk_email_batch_items')) {
            Schema::create('bp_bulk_email_batch_items', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('batch_id')->constrained('bp_bulk_email_batches')->cascadeOnDelete();
                $table->string('partner_type', 30)->index();
                $table->string('partner_id', 36)->index();
                $table->string('partner_name', 255)->nullable();
                $table->string('partner_code', 50)->nullable();
                $table->string('resolved_email', 255)->nullable();
                $table->string('resolved_name', 255)->nullable();
                $table->string('contact_id', 36)->nullable();
                $table->string('email_source', 50)->default('general');
                $table->char('email_message_id', 26)->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->string('skip_reason', 100)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->foreign('email_message_id')
                    ->references('id')->on('email_messages')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bp_bulk_email_batch_items');
        Schema::dropIfExists('bp_bulk_email_batches');
    }
};
