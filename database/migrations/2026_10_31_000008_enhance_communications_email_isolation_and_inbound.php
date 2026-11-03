<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add dedicated sender columns on email_messages for incoming messages
        Schema::table('email_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('email_messages', 'sender_email')) {
                $table->string('sender_email', 150)->nullable()->after('sender_profile_id');
            }
            if (! Schema::hasColumn('email_messages', 'sender_name')) {
                $table->string('sender_name', 150)->nullable()->after('sender_email');
            }

            $table->index(['mailbox_id', 'folder', 'is_deleted'], 'email_msg_mb_fldr_del_idx');
        });

        // 2. Add last_synced_uidvalidity on email_mailboxes for RFC 3501 UIDVALIDITY tracking
        Schema::table('email_mailboxes', function (Blueprint $table) {
            if (! Schema::hasColumn('email_mailboxes', 'last_synced_uidvalidity')) {
                $table->unsignedBigInteger('last_synced_uidvalidity')->nullable()->after('last_synced_uid');
            }
        });

        // 3. Make email_recipients.type support 'from' participant type
        Schema::table('email_recipients', function (Blueprint $table) {
            $table->string('type', 20)->default('to')->change();
        });
    }

    public function down(): void
    {
        Schema::table('email_recipients', function (Blueprint $table) {
            $table->string('type', 20)->default('to')->change();
        });

        Schema::table('email_mailboxes', function (Blueprint $table) {
            if (Schema::hasColumn('email_mailboxes', 'last_synced_uidvalidity')) {
                $table->dropColumn('last_synced_uidvalidity');
            }
        });

        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropIndex('email_msg_mb_fldr_del_idx');

            if (Schema::hasColumn('email_messages', 'sender_name')) {
                $table->dropColumn('sender_name');
            }
            if (Schema::hasColumn('email_messages', 'sender_email')) {
                $table->dropColumn('sender_email');
            }
        });
    }
};
