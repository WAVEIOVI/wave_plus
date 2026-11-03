<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeavePolicy extends Model
{
    use HasUlids;

    protected $table = 'hr_leave_policies';

    protected $fillable = [
        'leave_type_id',
        'department_id',
        'accrual_rate_per_month',
        'max_carryover_days',
    ];

    protected function casts(): array
    {
        return [
            'accrual_rate_per_month' => 'float',
            'max_carryover_days' => 'float',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
