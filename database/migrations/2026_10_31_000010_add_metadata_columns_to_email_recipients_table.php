<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_recipients', function (Blueprint $table) {
            if (! Schema::hasColumn('email_recipients', 'recipient_type')) {
                $table->string('recipient_type', 50)->nullable()->after('email');
            }
            if (! Schema::hasColumn('email_recipients', 'recipient_id')) {
                $table->string('recipient_id', 36)->nullable()->after('recipient_type');
            }
            if (! Schema::hasColumn('email_recipients', 'email_source')) {
                $table->string('email_source', 30)->nullable()->after('recipient_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_recipients', function (Blueprint $table) {
            $table->dropColumn(['recipient_type', 'recipient_id', 'email_source']);
        });
    }
};
