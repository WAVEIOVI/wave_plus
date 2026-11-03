<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. email_mailboxes (Shared company mailboxes for IMAP/SMTP) ──
        if (! Schema::hasTable('email_mailboxes')) {
            Schema::create('email_mailboxes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100);
                $table->string('email', 150)->unique();
                $table->string('department', 50)->index(); // sales, support, finance, hr, direction
                $table->unsignedBigInteger('sender_profile_id')->nullable();
                $table->string('imap_host', 150)->nullable();
                $table->unsignedSmallInteger('imap_port')->default(993);
                $table->string('imap_encryption', 10)->default('ssl'); // ssl, tls, or null
                $table->string('imap_username', 150)->nullable();
                $table->text('imap_password')->nullable(); // encrypted
                $table->string('imap_folder', 50)->default('INBOX');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('last_synced_at')->nullable();
                $table->unsignedBigInteger('last_synced_uid')->nullable();
                $table->timestamps();

                $table->foreign('sender_profile_id')
                    ->references('id')->on('email_sender_profiles')
                    ->nullOnDelete();
            });
        }

        // ── 2. email_conversations (Shared Inboxes & Threaded Conversations) ──
        if (! Schema::hasTable('email_conversations')) {
            Schema::create('email_conversations', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->unsignedBigInteger('mailbox_id')->nullable();
                $table->string('department', 50)->nullable()->index();
                $table->string('subject', 255);
                $table->enum('status', ['open', 'pending', 'resolved', 'closed'])->default('open')->index();
                $table->char('assigned_to', 26)->nullable()->index();
                $table->char('assigned_by', 26)->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('first_message_at')->nullable();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->boolean('is_starred')->default(false)->index();
                $table->boolean('is_archived')->default(false)->index();
                $table->string('participant_names', 500)->nullable();
                $table->timestamps();

                $table->foreign('mailbox_id')
                    ->references('id')->on('email_mailboxes')
                    ->nullOnDelete();

                $table->foreign('assigned_to')
                    ->references('id')->on('users')
                    ->nullOnDelete();

                $table->foreign('assigned_by')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }

        // ── 3. email_conversation_notes (Internal notes on conversations) ──
        if (! Schema::hasTable('email_conversation_notes')) {
            Schema::create('email_conversation_notes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('conversation_id', 26);
                $table->char('user_id', 26);
                $table->text('content');
                $table->timestamps();

                $table->foreign('conversation_id')
                    ->references('id')->on('email_conversations')
                    ->cascadeOnDelete();

                $table->foreign('user_id')
                    ->references('id')->on('users')
                    ->cascadeOnDelete();

                $table->index(['conversation_id', 'created_at']);
            });
        }

        // ── 4. Extend email_messages for threading, folders, and IMAP sync ──
        Schema::table('email_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('email_messages', 'conversation_id')) {
                $table->char('conversation_id', 26)->nullable()->after('id');
                $table->foreign('conversation_id')
                    ->references('id')->on('email_conversations')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('email_messages', 'mailbox_id')) {
                $table->unsignedBigInteger('mailbox_id')->nullable()->after('conversation_id');
                $table->foreign('mailbox_id')
                    ->references('id')->on('email_mailboxes')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('email_messages', 'direction')) {
                $table->enum('direction', ['outgoing', 'incoming'])->default('outgoing')->after('mailbox_id')->index();
            }

            if (! Schema::hasColumn('email_messages', 'folder')) {
                $table->string('folder', 30)->default('inbox')->after('direction')->index();
            }

            if (! Schema::hasColumn('email_messages', 'message_id')) {
                $table->string('message_id', 255)->nullable()->after('folder')->index();
            }

            if (! Schema::hasColumn('email_messages', 'in_reply_to')) {
                $table->string('in_reply_to', 255)->nullable()->after('message_id')->index();
            }

            if (! Schema::hasColumn('email_messages', 'references')) {
                $table->text('references')->nullable()->after('in_reply_to');
            }

            if (! Schema::hasColumn('email_messages', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('references')->index();
            }

            if (! Schema::hasColumn('email_messages', 'is_read')) {
                $table->boolean('is_read')->default(false)->after('scheduled_at')->index();
            }

            if (! Schema::hasColumn('email_messages', 'is_starred')) {
                $table->boolean('is_starred')->default(false)->after('is_read')->index();
            }

            if (! Schema::hasColumn('email_messages', 'is_deleted')) {
                $table->boolean('is_deleted')->default(false)->after('is_starred')->index();
            }

            if (! Schema::hasColumn('email_messages', 'uid')) {
                $table->unsignedBigInteger('uid')->nullable()->after('is_deleted')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $columnsToDrop = [
                'uid', 'is_deleted', 'is_starred', 'is_read', 'scheduled_at',
                'references', 'in_reply_to', 'message_id', 'folder', 'direction',
            ];
            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('email_messages', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('email_messages', 'mailbox_id')) {
                $table->dropForeign(['mailbox_id']);
                $table->dropColumn('mailbox_id');
            }
            if (Schema::hasColumn('email_messages', 'conversation_id')) {
                $table->dropForeign(['conversation_id']);
                $table->dropColumn('conversation_id');
            }
        });

        Schema::dropIfExists('email_conversation_notes');
        Schema::dropIfExists('email_conversations');
        Schema::dropIfExists('email_mailboxes');
    }
};
