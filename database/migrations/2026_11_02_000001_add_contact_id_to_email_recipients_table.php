<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('email_recipients', function (Blueprint $table) {
            if (! Schema::hasColumn('email_recipients', 'contact_id')) {
                $table->string('contact_id', 36)->nullable()->after('recipient_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_recipients', function (Blueprint $table) {
            if (Schema::hasColumn('email_recipients', 'contact_id')) {
                $table->dropColumn('contact_id');
            }
        });
    }
};
