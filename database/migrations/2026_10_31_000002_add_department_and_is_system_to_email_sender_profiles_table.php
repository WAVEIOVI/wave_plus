<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_sender_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('email_sender_profiles', 'department')) {
                $table->string('department', 100)->nullable()->after('reply_to');
            }
            if (! Schema::hasColumn('email_sender_profiles', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('department');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_sender_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('email_sender_profiles', 'is_system')) {
                $table->dropColumn('is_system');
            }
            if (Schema::hasColumn('email_sender_profiles', 'department')) {
                $table->dropColumn('department');
            }
        });
    }
};
