<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $site_id
 * @property string|null $parent_area_id
 * @property string|null $code
 * @property string $name
 * @property string $area_type
 * @property string|null $classification
 * @property string|null $accessibility
 * @property int|null $level_number
 * @property string|null $description
 * @property string $status
 * @property int $sort_order
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Modules\ClientAssets\Models\ClientSite|null $site
 * @property-read \App\Modules\ClientAssets\Models\ClientSiteArea|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Modules\ClientAssets\Models\ClientSiteArea[] $children
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Modules\ClientAssets\Models\ClientSiteArea[] $allChildren
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Modules\ClientAssets\Models\ClientEquipment[] $equipment
 */
class ClientSiteArea extends Model
{
    use GeneratesReference, HasUlids, SoftDeletes;

    public const DOCUMENT_TYPE_PREFIX = 'CSA';

    protected $table = 'client_site_areas';

    protected $fillable = [
        'site_id',
        'parent_area_id',
        'code',
        'name',
        'area_type',
        'classification',
        'accessibility',
        'level_number',
        'description',
        'status',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'level_number' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // ─── Reference generation ────────────────────────────────────

    protected function getReferenceColumn(): string
    {
        return 'code';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        $prefix = match ($this->area_type) {
            'building' => 'BLD',
            'floor' => 'FL',
            'room' => 'RM',
            'exterior' => 'EXT',
            'roof' => 'RF',
            'basement' => 'BS',
            default => 'ZN',
        };

        return sprintf('%s-%02d', $prefix, $nextValue);
    }

    protected function getDocumentSequenceName(): string
    {
        return static::DOCUMENT_TYPE_PREFIX;
    }

    // ─── Relationships ───────────────────────────────────────────

    public function site(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'site_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_area_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_area_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(ClientEquipment::class, 'area_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_area_id');
    }

    /**
     * Get the full breadcrumb hierarchy path string (e.g. "Zone A > Production Hall > Line 1").
     */
    public function getHierarchyPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;
        $visited = [$this->id];

        while ($parent && !in_array($parent->id, $visited, true)) {
            $visited[] = $parent->id;
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Get all descendant area IDs recursively.
     */
    public function getAllDescendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }
}
