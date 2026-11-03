<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class PublicHoliday extends Model
{
    use HasFactory, HasTranslations, HasUlids;

    protected $table = 'hr_public_holidays';

    public array $translatable = ['name'];

    protected $fillable = [
        'name',
        'date',
        'end_date',
        'year',
        'is_recurring',
        'is_half_day',
        'applies_to_all',
        'country_code',
        'department_id',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'end_date' => 'date',
            'year' => 'integer',
            'is_recurring' => 'boolean',
            'is_half_day' => 'boolean',
            'applies_to_all' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where(function (Builder $q) use ($year) {
            $q->where('year', $year)
                ->orWhere('is_recurring', true);
        });
    }

    /**
     * Check if this holiday applies to a specific date.
     */
    public function matchesDate(\Carbon\CarbonInterface $targetDate): bool
    {
        if ($this->is_recurring) {
            $m = (int) $this->date->format('m');
            $d = (int) $this->date->format('d');
            return ((int) $targetDate->format('m') === $m) && ((int) $targetDate->format('d') === $d);
        }

        $dateStr = $targetDate->toDateString();
        $startStr = $this->date->toDateString();
        $endStr = $this->end_date ? $this->end_date->toDateString() : $startStr;

        return $dateStr >= $startStr && $dateStr <= $endStr;
    }
}
