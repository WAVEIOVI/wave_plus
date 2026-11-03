<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSkill extends Model
{
    use HasUlids;

    protected $table = 'hr_employee_skills';

    protected $fillable = [
        'employee_id',
        'skill_id',
        'level',
        'acquired_date',
        'is_verified',
        'verified_by',
        'verified_at',
        'expiry_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'acquired_date' => 'date',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'expiry_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
