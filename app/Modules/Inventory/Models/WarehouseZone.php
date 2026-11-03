<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property string $site_id
 * @property string $code
 * @property array $name
 * @property string $zone_type
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read WarehouseSite $site
 * @property-read \Illuminate\Database\Eloquent\Collection|WarehouseLocation[] $locations
 */
class WarehouseZone extends Model
{
    use HasTranslations, HasUlids, SoftDeletes;

    protected $table = 'warehouse_zones';

    public array $translatable = ['name'];

    protected $fillable = [
        'site_id',
        'code',
        'name',
        'zone_type',
        'status',
        'notes',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function site(): BelongsTo
    {
        return $this->belongsTo(WarehouseSite::class, 'site_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'zone_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to only active zones.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by zone type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('zone_type', $type);
    }
}
