<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class WorkTeam extends Model
{
    use HasFactory, HasUlids, HasTranslations, SoftDeletes;

    protected $table = 'hr_work_teams';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'code',
        'name',
        'department_id',
        'team_leader_id',
        'team_type',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'team_leader_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkTeamMember::class, 'team_id')->with('employee');
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(WorkTeamMember::class, 'team_id')
            ->whereNull('left_at')
            ->with('employee');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
