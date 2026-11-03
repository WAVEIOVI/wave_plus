<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $zone_id
 * @property string $barcode
 * @property string|null $aisle
 * @property string|null $rack
 * @property string|null $shelf
 * @property string|null $bin
 * @property string $location_type
 * @property float|null $max_weight_kg
 * @property float|null $max_volume_m3
 * @property bool $is_active
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string $full_path
 * @property-read WarehouseZone $zone
 * @property-read \Illuminate\Database\Eloquent\Collection|StockItem[] $stockItems
 */
class WarehouseLocation extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'warehouse_locations';

    protected $fillable = [
        'zone_id',
        'barcode',
        'aisle',
        'rack',
        'shelf',
        'bin',
        'location_type',
        'max_weight_kg',
        'max_volume_m3',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'max_weight_kg' => 'decimal:2',
            'max_volume_m3' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function zone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class, 'zone_id');
    }

    /**
     * Convenience: traverse up to the site through the zone.
     * Note: This returns the relation from the parent zone.
     */
    public function site(): BelongsTo
    {
        return $this->zone->site();
    }

    /**
     * All stock items stored at this location.
     */
    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class, 'location_id');
    }

    // ─── Accessors ───────────────────────────────────────────────

    /**
     * Human-readable label: "A-01-03-B" (aisle-rack-shelf-bin).
     */
    public function getFullPathAttribute(): string
    {
        return collect([$this->aisle, $this->rack, $this->shelf, $this->bin])
            ->filter()
            ->implode('-');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to only active locations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
