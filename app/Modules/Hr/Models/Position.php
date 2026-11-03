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

class Position extends Model
{
    use HasFactory, HasUlids, HasTranslations, SoftDeletes;

    protected $table = 'hr_positions';

    public array $translatable = ['title', 'description', 'requirements'];

    protected $fillable = [
        'department_id',
        'code',
        'title',
        'description',
        'standard_hourly_rate',
        'requirements',
        'status',
    ];

    protected $casts = [
        'standard_hourly_rate' => 'decimal:2',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'position_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
