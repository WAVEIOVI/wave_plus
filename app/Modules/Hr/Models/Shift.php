<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Shift extends Model
{
    use HasFactory, HasUlids, HasTranslations;

    protected $table = 'hr_shifts';

    public array $translatable = ['name'];

    protected $fillable = [
        'code',
        'name',
        'start_time',
        'end_time',
        'break_duration_minutes',
        'is_overnight',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_overnight' => 'boolean',
            'break_duration_minutes' => 'integer',
        ];
    }

    /**
     * Calculate the shift duration in minutes, correctly handling overnight shifts.
     * For overnight shifts, end_time is on the next calendar day.
     */
    public function getDurationMinutesAttribute(): int
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        if ($this->is_overnight && $end->lte($start)) {
            $end->addDay();
        }

        $rawMinutes = (int) $start->diffInMinutes($end);

        return max(0, $rawMinutes - ($this->break_duration_minutes ?? 0));
    }

    /**
     * Return the effective work hours for this shift.
     */
    public function getDurationHoursAttribute(): float
    {
        return round($this->duration_minutes / 60, 2);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'shift_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
