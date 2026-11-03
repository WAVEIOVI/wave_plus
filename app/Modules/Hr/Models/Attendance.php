<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasUlids;

    protected $table = 'hr_attendances';

    protected $fillable = [
        'employee_id',
        'date',
        'scheduled_in',
        'scheduled_out',
        'actual_in',
        'actual_out',
        'scheduled_hours',
        'worked_hours',
        'late_minutes',
        'early_departure_minutes',
        'overtime_hours',
        'status',
        'coverage_status',
        'schedule_id',
        'covering_leave_id',
        'is_corrected',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'scheduled_hours' => 'float',
            'worked_hours' => 'float',
            'late_minutes' => 'integer',
            'early_departure_minutes' => 'integer',
            'overtime_hours' => 'float',
            'is_corrected' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function coveringLeave(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'covering_leave_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class, 'attendance_id')->orderByDesc('created_at');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(AttendanceException::class, 'attendance_id')->orderBy('created_at');
    }
}
