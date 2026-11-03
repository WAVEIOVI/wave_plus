<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class TrainingSession extends Model
{
    use HasTranslations, HasUlids;

    protected $table = 'hr_training_sessions';

    public array $translatable = ['trainer'];

    protected $fillable = [
        'training_program_id',
        'session_code',
        'trainer',
        'start_date',
        'end_date',
        'location',
        'max_participants',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'max_participants' => 'integer',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EmployeeTraining::class, 'training_session_id')->with('employee');
    }
}
