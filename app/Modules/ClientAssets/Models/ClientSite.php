<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $client_id
 * @property int|null $bp_address_id
 * @property string $site_code
 * @property string $name
 * @property string|null $description
 * @property string $site_type
 * @property string $status
 * @property string|null $risk_class
 * @property string|null $notes
 * @property string|null $operating_hours
 * @property string|null $access_instructions
 * @property string|null $safety_instructions
 * @property string|null $special_instructions
 * @property \Illuminate\Support\Carbon|null $opened_at
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Modules\BusinessPartners\Models\Client|null $client
 * @property-read \App\Modules\BusinessPartners\Models\BpAddress|null $address
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Modules\ClientAssets\Models\ClientSiteArea[] $areas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Modules\ClientAssets\Models\ClientSiteArea[] $rootAreas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Modules\ClientAssets\Models\ClientEquipment[] $equipment
 */
class ClientSite extends Model implements HasMedia
{
    use GeneratesReference, HasUlids, InteractsWithMedia, SoftDeletes;

    protected $table = 'client_sites';

    public const DOCUMENT_TYPE_PREFIX = 'CS';

    protected $fillable = [
        'client_id',
        'bp_address_id',
        'site_code',
        'name',
        'description',
        'site_type',
        'status',
        'risk_class',
        'notes',
        'operating_hours',
        'access_instructions',
        'safety_instructions',
        'special_instructions',
        'opened_at',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'closed_at' => 'date',
        ];
    }

    // ─── Reference generation ────────────────────────────────────

    protected function getReferenceColumn(): string
    {
        return 'site_code';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('CS-%05d', $nextValue);
    }

    protected function getDocumentSequenceName(): string
    {
        return static::DOCUMENT_TYPE_PREFIX;
    }

    // ─── Media Collections ──────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('public');

        $this->addMediaCollection('documents')
            ->useDisk('local');

        $this->addMediaCollection('site_plans')
            ->useDisk('local');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->performOnCollections('photos')
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(1200)
            ->height(1200)
            ->performOnCollections('photos')
            ->nonQueued();
    }

    public function getMainPhoto(): ?Media
    {
        $photos = $this->getMedia('photos');
        $primary = $photos->first(fn (Media $m) => $m->getCustomProperty('is_main') === true);

        return $primary ?: $photos->first();
    }

    // ─── Relationships ───────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(BpAddress::class, 'bp_address_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(ClientSiteArea::class, 'site_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function rootAreas(): HasMany
    {
        return $this->hasMany(ClientSiteArea::class, 'site_id')
            ->whereNull('parent_area_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(ClientEquipment::class, 'site_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeActive(Builder $query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForClient(Builder $query, string $clientId)
    {
        return $query->where('client_id', $clientId);
    }
}
