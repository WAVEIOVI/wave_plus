<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceException extends Model
{
    use HasUlids;

    protected $table = 'hr_attendance_exceptions';

    public const TYPES = [
        'early_arrival',
        'late_arrival',
        'early_departure',
        'overtime',
        'missing_checkout',
        'missing_checkin',
        'invalid_event_sequence',
        'leave_mismatch',
        'schedule_mismatch',
        'other',
    ];

    public const REVIEW_OPEN = 'open';

    public const REVIEW_ACKNOWLEDGED = 'acknowledged';

    public const REVIEW_RESOLVED = 'resolved';

    public const REVIEW_DISMISSED = 'dismissed';

    protected $fillable = [
        'employee_id',
        'date',
        'time_clock_event_id',
        'attendance_id',
        'exception_type',
        'expected_time',
        'actual_time',
        'deviation_minutes',
        'covering_leave_request_id',
        'review_status',
        'notes',
        'reviewed_by_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'reviewed_at' => 'datetime',
            'deviation_minutes' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function clockEvent(): BelongsTo
    {
        return $this->belongsTo(TimeClockEvent::class, 'time_clock_event_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    public function coveringLeave(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'covering_leave_request_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('review_status', self::REVIEW_OPEN);
    }

    public function scopeForEmployee($query, string $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
