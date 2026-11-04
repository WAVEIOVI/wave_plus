<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Align Summer and Saturday shifts with Tunisian working-time configurations:
        // Summer: 08:00–14:30, 30-minute break.
        // Saturday: 08:00–13:30, 0-minute break.
        // Preserves existing operational schedules while updating template shift definitions.
        DB::table('hr_shifts')->where('code', 'SUMMER')->update([
            'start_time' => '08:00:00',
            'end_time' => '14:30:00',
            'break_duration_minutes' => 30,
        ]);

        DB::table('hr_shifts')->where('code', 'SATURDAY')->update([
            'start_time' => '08:00:00',
            'end_time' => '13:30:00',
            'break_duration_minutes' => 0,
        ]);
    }

    public function down(): void
    {
        DB::table('hr_shifts')->where('code', 'SUMMER')->update([
            'start_time' => '07:30:00',
            'end_time' => '14:00:00',
            'break_duration_minutes' => 30,
        ]);

        DB::table('hr_shifts')->where('code', 'SATURDAY')->update([
            'start_time' => '08:00:00',
            'end_time' => '14:00:00',
            'break_duration_minutes' => 0,
        ]);
    }
};
