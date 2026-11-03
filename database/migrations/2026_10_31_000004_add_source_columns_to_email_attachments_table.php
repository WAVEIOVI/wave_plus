<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('email_attachments', 'source')) {
                $table->string('source', 20)->default('upload')->after('mime_type');
            }
            if (! Schema::hasColumn('email_attachments', 'source_type')) {
                $table->string('source_type', 50)->nullable()->after('source');
            }
            if (! Schema::hasColumn('email_attachments', 'source_id')) {
                $table->string('source_id', 36)->nullable()->after('source_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_attachments', function (Blueprint $table) {
            foreach (['source_id', 'source_type', 'source'] as $column) {
                if (Schema::hasColumn('email_attachments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
