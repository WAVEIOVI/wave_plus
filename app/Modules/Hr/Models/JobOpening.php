<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOpening extends Model
{
    use GeneratesReference, HasUlids, SoftDeletes;

    protected $table = 'hr_job_openings';

    public const DOCUMENT_TYPE_PREFIX = 'JOB';

    protected $fillable = [
        'reference',
        'title',
        'department_id',
        'position_id',
        'openings_count',
        'status',
        'description',
        'requirements',
        'published_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'openings_count' => 'integer',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected function getReferenceColumn(): string
    {
        return 'reference';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('JOB-%05d', $nextValue);
    }

    protected function getDocumentSequenceName(): string
    {
        return static::DOCUMENT_TYPE_PREFIX;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'job_opening_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'job_opening_id');
    }
}
