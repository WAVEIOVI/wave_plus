<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTraining extends Model
{
    use HasUlids;

    protected $table = 'hr_employee_trainings';

    protected $fillable = [
        'training_session_id',
        'employee_id',
        'status',
        'score',
        'completion_date',
        'certificate_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'completion_date' => 'date',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
