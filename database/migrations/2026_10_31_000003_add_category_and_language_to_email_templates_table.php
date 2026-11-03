<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('email_templates', 'category')) {
                $table->string('category', 50)->nullable()->after('slug');
            }
            if (! Schema::hasColumn('email_templates', 'language')) {
                $table->string('language', 10)->default('fr')->after('category');
            }
            $table->index(['category', 'language']);
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropIndex(['category', 'language']);
            if (Schema::hasColumn('email_templates', 'language')) {
                $table->dropColumn('language');
            }
            if (Schema::hasColumn('email_templates', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
