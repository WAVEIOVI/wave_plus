<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasUlids;

    protected $table = 'hr_schedules';

    /**
     * Lifecycle statuses — represent where the schedule record is in its
     * own workflow, independent of attendance and coverage.
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Coverage statuses — represent how approved leave affects the planned work.
     * These are set by the attendance calculation / leave approval flow.
     */
    public const COVERAGE_NORMAL = 'normal';

    public const COVERAGE_FULL_DAY_LEAVE = 'full_day_leave';

    public const COVERAGE_PARTIAL_LEAVE = 'partial_leave';

    public const COVERAGE_ABSENCE = 'absence';

    public const COVERAGE_CONFLICT = 'conflict';

    protected $fillable = [
        'employee_id',
        'shift_id',
        'date',
        'status',           // original column — kept for backward compat
        'lifecycle_status', // new column — preferred status field
        'coverage_status',
        'custom_start_time',
        'custom_end_time',
        'is_overnight_override',
        'plan_batch_id',
        'notes',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_overnight_override' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    // ─── Computed helpers ─────────────────────────────────────────

    /**
     * Returns the effective start time for this schedule day.
     * Uses custom_start_time if set, otherwise falls back to the shift default.
     */
    public function getEffectiveStartTimeAttribute(): ?string
    {
        return $this->custom_start_time ?? $this->shift?->start_time;
    }

    /**
     * Returns the effective end time for this schedule day.
     */
        /** Hours before this cutoff, checked against the previous day's shift,
     *  are treated as closing an overnight shift (e.g. a 06:00 clock-out). */
    public const OVERNIGHT_MORNING_CUTOFF = 12;

    public function getEffectiveEndTimeAttribute(): ?string
    {
        return $this->custom_end_time ?? $this->shift?->end_time;
    }

    /**
     * Whether this schedule or its shift crosses midnight.
     */
    public function getIsOvernightAttribute(): bool
    {
        if ($this->is_overnight_override) {
            return true;
        }

        return $this->shift?->is_overnight ?? false;
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNotIn('lifecycle_status', [self::STATUS_CANCELLED]);
    }

    public function scopePlanned($query)
    {
        return $query->where('lifecycle_status', self::STATUS_PLANNED);
    }

    public function scopeForDateRange($query, string $start, string $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }
}
