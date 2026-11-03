<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use GeneratesReference, HasUlids, SoftDeletes;

    protected $table = 'hr_leave_requests';

    public const DOCUMENT_TYPE_PREFIX = 'LV';

    protected $fillable = [
        'reference',
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'duration_type',   // full_day | partial_day | hourly
        'start_time',      // for hourly leave only
        'end_time',        // for hourly leave only
        'total_days',
        'reason',
        'status',
        'requested_by_id',
        'approver_id',
        'approved_at',
        'rejection_reason',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'float',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Is this an hourly or partial-day leave request?
     */
    public function isHourlyLeave(): bool
    {
        return in_array($this->duration_type, ['hourly', 'partial_day']);
    }

    /**
     * Is this leave approved?
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    protected function getReferenceColumn(): string
    {
        return 'reference';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('LV-%05d', $nextValue);
    }

    protected function getDocumentSequenceName(): string
    {
        return static::DOCUMENT_TYPE_PREFIX;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by_id');
    }
}
