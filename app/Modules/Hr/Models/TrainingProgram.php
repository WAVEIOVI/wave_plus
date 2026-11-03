<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class TrainingProgram extends Model
{
    use HasFactory, HasUlids, HasTranslations;

    protected $table = 'hr_training_programs';

    public array $translatable = ['name', 'description', 'provider'];

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'provider',
        'duration_hours',
        'target_skill_id',
        'target_certification_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function targetSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'target_skill_id');
    }

    public function targetCertification(): BelongsTo
    {
        return $this->belongsTo(Certification::class, 'target_certification_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'training_program_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
