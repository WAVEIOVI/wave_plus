<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Central HR Attendance Configuration Model.
 *
 * All attendance grace periods and tolerance values come from here.
 * Never hard-code magic numbers in services; always use this model.
 *
 * Usage:
 *   $config = AttendanceConfig::global();
 *   $grace  = $config->late_checkin_grace_minutes;
 */
class AttendanceConfig extends Model
{
    use HasUlids;

    protected $table = 'hr_attendance_config';

    protected $fillable = [
        'scope',
        'scope_id',
        'early_checkin_grace_minutes',
        'late_checkin_grace_minutes',
        'early_checkout_grace_minutes',
        'late_checkout_grace_minutes',
        'hourly_leave_tolerance_minutes',
        'min_hourly_leave_unit_minutes',
    ];

    protected function casts(): array
    {
        return [
            'early_checkin_grace_minutes' => 'integer',
            'late_checkin_grace_minutes' => 'integer',
            'early_checkout_grace_minutes' => 'integer',
            'late_checkout_grace_minutes' => 'integer',
            'hourly_leave_tolerance_minutes' => 'integer',
            'min_hourly_leave_unit_minutes' => 'integer',
        ];
    }

    /**
     * Retrieve the global configuration row.
     * Result is cached for 60 minutes to avoid repeated DB hits on every clock event.
     */
    public static function global(): self
    {
        return Cache::remember('hr_attendance_config_global', 3600, function () {
            return self::where('scope', 'global')->where('scope_id', null)->firstOrFail();
        });
    }

    /**
     * Clear the config cache (call after saving changes).
     */
    public static function clearCache(): void
    {
        Cache::forget('hr_attendance_config_global');
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::clearCache());
        static::deleted(fn () => self::clearCache());
    }
}
