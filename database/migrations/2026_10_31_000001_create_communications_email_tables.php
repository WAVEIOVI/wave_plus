<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── email_sender_profiles ─────────────────────────────────────────
        if (! Schema::hasTable('email_sender_profiles')) {
            Schema::create('email_sender_profiles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100);
                $table->string('email', 150);
                $table->string('reply_to', 150)->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique('email');
            });
        }

        // ── email_templates ───────────────────────────────────────────────
        if (! Schema::hasTable('email_templates')) {
            Schema::create('email_templates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('slug', 100);
                $table->string('name', 150);
                $table->string('subject', 255);
                $table->longText('body_html');
                $table->text('body_text')->nullable();
                $table->json('variables')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique('slug');
            });
        }

        // ── email_messages ────────────────────────────────────────────────
        if (! Schema::hasTable('email_messages')) {
            Schema::create('email_messages', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->unsignedBigInteger('sender_profile_id')->nullable();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->string('subject', 255);
                $table->longText('body_html');
                $table->text('body_text')->nullable();
                $table->enum('status', ['draft', 'queued', 'sending', 'sent', 'failed'])->default('draft');
                $table->char('idempotency_key', 36)->nullable()->unique();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('last_error')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedTinyInteger('max_attempts')->default(3);
                $table->char('created_by', 26)->nullable();
                $table->timestamps();

                $table->foreign('sender_profile_id')
                    ->references('id')->on('email_sender_profiles')
                    ->nullOnDelete();

                $table->foreign('template_id')
                    ->references('id')->on('email_templates')
                    ->nullOnDelete();

                $table->foreign('created_by')
                    ->references('id')->on('users')
                    ->nullOnDelete();

                $table->index('status');
                $table->index('created_by');
            });
        }

        // ── email_recipients ──────────────────────────────────────────────
        if (! Schema::hasTable('email_recipients')) {
            Schema::create('email_recipients', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('email_message_id', 26);
                $table->enum('type', ['to', 'cc', 'bcc'])->default('to');
                $table->string('name', 150)->nullable();
                $table->string('email', 150);

                $table->foreign('email_message_id')
                    ->references('id')->on('email_messages')
                    ->cascadeOnDelete();

                $table->index(['email_message_id', 'type']);
            });
        }

        // ── email_attachments ─────────────────────────────────────────────
        if (! Schema::hasTable('email_attachments')) {
            Schema::create('email_attachments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('email_message_id', 26);
                $table->string('filename', 255);
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('storage_disk', 30)->nullable();
                $table->string('storage_path', 500)->nullable();
                $table->longText('content_b64')->nullable();
                $table->timestamps();

                $table->foreign('email_message_id')
                    ->references('id')->on('email_messages')
                    ->cascadeOnDelete();
            });
        }

        // ── email_associations ────────────────────────────────────────────
        if (! Schema::hasTable('email_associations')) {
            Schema::create('email_associations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('email_message_id', 26);
                $table->string('associable_type', 200);
                $table->string('associable_id', 36);
                $table->timestamps();

                $table->foreign('email_message_id')
                    ->references('id')->on('email_messages')
                    ->cascadeOnDelete();

                $table->index(['associable_type', 'associable_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_associations');
        Schema::dropIfExists('email_attachments');
        Schema::dropIfExists('email_recipients');
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_sender_profiles');
    }
};
