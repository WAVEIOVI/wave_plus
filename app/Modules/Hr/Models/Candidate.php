<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Candidate extends Model implements HasMedia
{
    use GeneratesReference, HasUlids, InteractsWithMedia, SoftDeletes;

    protected $table = 'hr_candidates';

    public const DOCUMENT_TYPE_PREFIX = 'CAN';

    protected $fillable = [
        'reference',
        'first_name',
        'last_name',
        'email',
        'phone',
        'status',
        'notes',
    ];

    protected function getReferenceColumn(): string
    {
        return 'reference';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('CAN-%05d', $nextValue);
    }

    protected function getDocumentSequenceName(): string
    {
        return static::DOCUMENT_TYPE_PREFIX;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('resume')
            ->useDisk('local')
            ->singleFile();

        $this->addMediaCollection('documents')
            ->useDisk('local');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'candidate_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'candidate_id');
    }
}
