<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkTeamMember extends Model
{
    use HasUlids;

    protected $table = 'hr_work_team_members';

    protected $fillable = [
        'team_id',
        'employee_id',
        'is_primary',
        'joined_at',
        'left_at',
        'role_in_team',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'joined_at' => 'date',
            'left_at' => 'date',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(WorkTeam::class, 'team_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
