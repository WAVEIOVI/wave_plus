<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property string $code
 * @property array $name
 * @property array|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $phone
 * @property string|null $email
 * @property int|null $manager_id
 * @property string $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|WarehouseZone[] $zones
 * @property-read \Illuminate\Database\Eloquent\Collection|WarehouseLocation[] $locations
 * @property-read User|null $manager
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
class WarehouseSite extends Model
{
    use HasTranslations, HasUlids, SoftDeletes;

    protected $table = 'warehouse_sites';

    public array $translatable = ['name'];

    protected $fillable = [
        'code',
        'name',
        'site_type',
        'vehicle_id',
        'mobile_status',
        'current_location',
        'location_notes',
        'activated_at',
        'deactivated_at',
        'address',
        'city',
        'country',
        'phone',
        'email',
        'manager_id',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Hr\Models\Vehicle::class, 'vehicle_id');
    }

    public function mobileAssignments(): HasMany
    {
        return $this->hasMany(MobileWarehouseAssignment::class, 'warehouse_site_id')
            ->orderByDesc('assigned_from');
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(MobileWarehouseAssignment::class, 'warehouse_site_id')
            ->where('is_current', true)
            ->latestOfMany('assigned_from');
    }

    public function zones(): HasMany
    {
        return $this->hasMany(WarehouseZone::class, 'site_id');
    }

    /**
     * All locations across all zones of this site.
     */
    public function locations(): HasManyThrough
    {
        return $this->hasManyThrough(WarehouseLocation::class, WarehouseZone::class, 'site_id', 'zone_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to only active sites.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to fixed warehouses only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFixed($query)
    {
        return $query->where('site_type', 'fixed');
    }

    /**
     * Scope to mobile warehouses only.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMobile($query)
    {
        return $query->where('site_type', 'mobile');
    }

    /**
     * Scope to mobile warehouses with assigned vehicles.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMobileWithVehicle($query)
    {
        return $query->where('site_type', 'mobile')
            ->whereNotNull('vehicle_id');
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getIsFixedAttribute(): bool
    {
        return $this->site_type === 'fixed';
    }

    public function getIsMobileAttribute(): bool
    {
        return $this->site_type === 'mobile';
    }

    public function getHasVehicleAttribute(): bool
    {
        return $this->is_mobile && $this->vehicle_id !== null;
    }
}
