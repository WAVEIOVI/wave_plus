<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use HasUlids;

    protected $table = 'hr_leave_balances';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'accrued_days',
        'approved_used_days',
        'pending_reserved_days',
        'available_days',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'accrued_days' => 'float',
            'approved_used_days' => 'float',
            'pending_reserved_days' => 'float',
            'available_days' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    /**
     * Recalculate available days dynamically.
     */
    public function recalculate(): void
    {
        $net = (float) $this->accrued_days - ((float) $this->approved_used_days + (float) $this->pending_reserved_days);
        $this->available_days = max(0, round($net, 2));
        $this->save();
    }
}
