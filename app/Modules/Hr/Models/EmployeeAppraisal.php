<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAppraisal extends Model
{
    use HasUlids;

    protected $table = 'hr_employee_appraisals';

    protected $fillable = [
        'appraisal_cycle_id',
        'employee_id',
        'reviewer_id',
        'overall_score',
        'status',
        'manager_feedback',
        'employee_feedback',
        'goals',
        'ratings',
    ];

    protected function casts(): array
    {
        return [
            'overall_score' => 'float',
            'goals' => 'array',
            'ratings' => 'array',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
