<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE email_messages MODIFY COLUMN status ENUM('draft', 'queued', 'sending', 'sent', 'failed', 'received') NOT NULL DEFAULT 'draft'");
        } else {
            Schema::table('email_messages', function (Blueprint $table) {
                $table->string('status', 20)->default('draft')->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE email_messages MODIFY COLUMN status ENUM('draft', 'queued', 'sending', 'sent', 'failed') NOT NULL DEFAULT 'draft'");
        } else {
            Schema::table('email_messages', function (Blueprint $table) {
                $table->string('status', 20)->default('draft')->change();
            });
        }
    }
};
