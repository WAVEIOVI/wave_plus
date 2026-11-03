<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $name
 * @property string $code
 * @property string|null $country_of_origin
 * @property string|null $warranty_policy_notes
 * @property string|null $logo_source
 * @property string|null $logo_domain
 * @property string|null $logo_external_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CatalogBrand extends Model implements HasMedia
{
    use HasUlids, InteractsWithMedia;

    protected $table = 'catalog_brands';

    protected $fillable = [
        'name',
        'code',
        'country_of_origin',
        'warranty_policy_notes',
        'logo_source',
        'logo_domain',
        'logo_external_url',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->useDisk('public')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(400)
            ->height(400)
            ->nonQueued();
    }

    // ─── Relationships ───────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(CatalogItem::class, 'brand_id');
    }

    /**
     * Resolve the logo the same way every other brandable entity does:
     * uploaded media wins, then a provider-hosted URL, then nothing.
     */
    public function getLogoUrl(): ?string
    {
        $mediaUrl = $this->getFirstMediaUrl('logo');

        if ($mediaUrl) {
            $appUrl = rtrim(config('app.url'), '/');
            $requestBase = rtrim(url('/'), '/');

            return $appUrl !== $requestBase ? str_replace($appUrl, $requestBase, $mediaUrl) : $mediaUrl;
        }

        return $this->logo_external_url ?: null;
    }
}
