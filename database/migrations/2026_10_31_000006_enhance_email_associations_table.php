<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_associations', function (Blueprint $table) {
            if (! Schema::hasColumn('email_associations', 'conversation_id')) {
                $table->char('conversation_id', 26)->nullable()->after('id');
                $table->foreign('conversation_id')
                    ->references('id')->on('email_conversations')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('email_associations', 'link_type')) {
                $table->string('link_type', 30)->default('manual')->after('associable_id')->index();
            }

            if (! Schema::hasColumn('email_associations', 'is_confirmed')) {
                $table->boolean('is_confirmed')->default(true)->after('link_type')->index();
            }

            if (! Schema::hasColumn('email_associations', 'confidence_score')) {
                $table->decimal('confidence_score', 3, 2)->default(1.00)->after('is_confirmed');
            }

            if (! Schema::hasColumn('email_associations', 'match_reason')) {
                $table->string('match_reason', 255)->nullable()->after('confidence_score');
            }

            if (! Schema::hasColumn('email_associations', 'linked_by')) {
                $table->char('linked_by', 26)->nullable()->after('match_reason');
                $table->foreign('linked_by')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            }
        });

        // Make email_message_id nullable so records can be linked directly at conversation level
        Schema::table('email_associations', function (Blueprint $table) {
            $table->char('email_message_id', 26)->nullable()->change();
            $table->index(['conversation_id', 'associable_type', 'associable_id'], 'email_assoc_conv_record_idx');
        });
    }

    public function down(): void
    {
        Schema::table('email_associations', function (Blueprint $table) {
            $table->dropIndex('email_assoc_conv_record_idx');

            if (Schema::hasColumn('email_associations', 'linked_by')) {
                $table->dropForeign(['linked_by']);
                $table->dropColumn('linked_by');
            }

            if (Schema::hasColumn('email_associations', 'match_reason')) {
                $table->dropColumn('match_reason');
            }

            if (Schema::hasColumn('email_associations', 'confidence_score')) {
                $table->dropColumn('confidence_score');
            }

            if (Schema::hasColumn('email_associations', 'is_confirmed')) {
                $table->dropColumn('is_confirmed');
            }

            if (Schema::hasColumn('email_associations', 'link_type')) {
                $table->dropColumn('link_type');
            }

            if (Schema::hasColumn('email_associations', 'conversation_id')) {
                $table->dropForeign(['conversation_id']);
                $table->dropColumn('conversation_id');
            }
        });
    }
};
