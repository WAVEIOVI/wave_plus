<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Modules\BusinessPartners\Models\Client;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $client_id
 * @property string|null $employee_ref
 * @property string $first_name
 * @property string $last_name
 * @property string|null $department
 * @property string|null $role
 * @property string $status
 * @property array|null $sizes
 * @property array|null $measurements
 */
class WorkwearEmployee extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'workwear_employees';

    protected $fillable = [
        'client_id',
        'employee_ref',
        'first_name',
        'last_name',
        'email',
        'phone',
        'department',
        'role',
        'position',
        'status',
        'sizes',
        'measurements',
        'workwear_requirements',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sizes' => 'array',
            'measurements' => 'array',
            'workwear_requirements' => 'array',
        ];
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // ─── Relationships ───────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkwearAssignment::class, 'employee_id')
            ->orderByDesc('assigned_at');
    }

    public function modelAssignments(): HasMany
    {
        return $this->hasMany(WorkwearEmployeeModel::class, 'employee_id')
            ->orderByDesc('assigned_at');
    }

    public function activeModelAssignments(): HasMany
    {
        return $this->hasMany(WorkwearEmployeeModel::class, 'employee_id')
            ->whereNull('ended_at');
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(WorkwearAssignment::class, 'employee_id')
            ->whereNull('returned_at');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
