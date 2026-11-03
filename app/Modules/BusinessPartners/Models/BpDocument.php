<?php

namespace App\Modules\BusinessPartners\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

/**
 * @property \Illuminate\Support\Carbon|null $issue_date
 * @property \Illuminate\Support\Carbon|null $effective_date
 * @property \Illuminate\Support\Carbon|null $expiration_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property Model|Client|Supplier|ServiceProvider|Organisation $documentable
 */
class BpDocument extends Model implements HasMedia
{
    use HasTranslations, InteractsWithMedia;
    use HasUlids;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('document')
            ->useDisk('local')
            ->singleFile();
    }

    protected $table = 'bp_documents';

    public array $translatable = ['title', 'notes'];

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'uploaded_by',
        'verified_by',
        'type',
        'title',
        'reference_number',
        'issue_date',
        'effective_date',
        'expiration_date',
        'auto_renewable',
        'status',
        'is_verified',
        'file_path',
        'file_name',
        'mime_type',
        'file_size_bytes',
        'notes',
        'metadata',
        'source',
        'page_count',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'effective_date' => 'date',
            'expiration_date' => 'date',
            'auto_renewable' => 'boolean',
            'is_verified' => 'boolean',
            'file_size_bytes' => 'integer',
            'page_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}