<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employee_appraisals', function (Blueprint $table) {
            $table->dateTime('reviewed_at')->nullable()->after('employee_feedback');
            $table->dateTime('acknowledged_at')->nullable()->after('reviewed_at');
            $table->ulid('acknowledged_by')->nullable()->after('acknowledged_at');

            $table->foreign('acknowledged_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_employee_appraisals', function (Blueprint $table) {
            $table->dropForeign(['acknowledged_by']);
            $table->dropColumn(['reviewed_at', 'acknowledged_at', 'acknowledged_by']);
        });
    }
};
