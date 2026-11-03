<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppraisalCycle extends Model
{
    use HasUlids;

    protected $table = 'hr_appraisal_cycles';

    protected $fillable = [
        'name',
        'cycle_type',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function appraisals(): HasMany
    {
        return $this->hasMany(EmployeeAppraisal::class, 'appraisal_cycle_id');
    }
}
