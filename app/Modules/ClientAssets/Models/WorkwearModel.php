<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Catalog\Models\CatalogItem;
use App\Traits\GeneratesReference;
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
 * @property string $model_ref
 * @property string $name
 * @property array|null $garment_types
 * @property string|null $fabric
 * @property array|null $colors
 * @property string|null $custom_type
 * @property string|null $silhouette
 * @property array|null $size_range
 * @property array|null $technical_specs
 * @property array|null $print_text
 * @property string|null $print_type
 * @property string|null $logo_placement
 * @property string|null $logo_ged_ref
 * @property string|null $en_standards
 * @property string|null $fr_rating
 * @property string|null $waterproofing
 * @property int|null $supplier_id
 * @property string|null $notes
 * @property string $status
 */
class WorkwearModel extends Model implements HasMedia
{
    use GeneratesReference, HasUlids, InteractsWithMedia, SoftDeletes;

    protected $table = 'workwear_models';

    public const DOCUMENT_TYPE_PREFIX = 'WM';

    protected $fillable = [
        'client_id',
        'catalog_item_id',
        'model_ref',
        'name',
        'garment_types',
        'fabric',
        'fabric_id',
        'colors',
        'custom_type',
        'silhouette_category',
        'silhouette_subcategory',
        'silhouette',
        'silhouette_item',
        'size_range',
        'technical_specs',
        'custom_made_requirements',
        'print_text',
        'print_type',
        'logo_placement',
        'logo_ged_ref',
        'en_standards',
        'fr_rating',
        'waterproofing',
        'certifications',
        'standards',
        'supplier_id',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'garment_types' => 'array',
            'colors' => 'array',
            'size_range' => 'array',
            'technical_specs' => 'array',
            'custom_made_requirements' => 'array',
            'certifications' => 'array',
            'standards' => 'array',
            'print_text' => 'array',
        ];
    }

    // ─── Reference generation ────────────────────────────────────

    protected function getReferenceColumn(): string
    {
        return 'model_ref';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('WM-%s-%s-%04d', $this->clientReferenceToken(), now()->format('ym'), $nextValue);
    }

    /**
     * Stable, human-meaningful token for the owning client.
     *
     * Prefers the client's business reference (e.g. CL00001). Falls back to the
     * random tail of the client ULID (the head is a timestamp, so it is not unique).
     */
    private function clientReferenceToken(): string
    {
        $ref = $this->client_id ? Client::query()->whereKey($this->client_id)->value('client_id') : null;

        if (is_string($ref) && $ref !== '') {
            return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $ref));
        }

        return $this->client_id ? strtoupper(substr((string) $this->client_id, -6)) : 'XXXXXX';
    }

    // ─── Media Collections ──────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logos')
            ->useDisk('public');

        $this->addMediaCollection('design_files')
            ->useDisk('local');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->performOnCollections('logos')
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(1200)
            ->height(1200)
            ->performOnCollections('logos')
            ->nonQueued();
    }

    // ─── Relationships ───────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function workwearFabric(): BelongsTo
    {
        return $this->belongsTo(WorkwearFabric::class, 'fabric_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkwearModelVersion::class, 'workwear_model_id')
            ->orderByDesc('version_number');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkwearItem::class, 'model_id');
    }

    public function placements(): HasMany
    {
        return $this->hasMany(WorkwearModelPlacement::class, 'workwear_model_id')
            ->orderBy('view')
            ->orderBy('sort_order');
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(WorkwearEmployeeModel::class, 'model_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(WorkwearModelOption::class, 'workwear_model_id')
            ->orderBy('sort_order');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
