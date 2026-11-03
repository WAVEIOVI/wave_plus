<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Modules\Hr\Models\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionEmployee extends Model
{
    use HasUlids;

    protected $table = 'mission_employees';

    protected $fillable = [
        'mission_id',
        'employee_id',
        'is_lead',
    ];

    protected $casts = [
        'is_lead' => 'boolean',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
