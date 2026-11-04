<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\OperationalHub\Models\WorkshopUnit;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Canonical fleet aggregate – owned by People & Skills / Resource Management.
 *
 * The table deliberately remains `vehicles`: Logistics, Inventory, Workshop and
 * Operational Hub all reference the same fleet records through vehicle_id.
 * Moving the master-data class here preserves all foreign keys while HR becomes
 * the authoritative source for create / update / lifecycle operations.
 */
class Vehicle extends Model
{
    use HasFactory, SoftDeletes;
    use HasUlids;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_IN_USE = 'in_use';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_OUT_OF_SERVICE = 'out_of_service';

    public const STATUS_RENTAL_ACTIVE = 'rental_active';

    public const STATUS_CLIENT_USE = 'client_use';

    public const STATUS_RETIRED = 'retired';

    public const STATUS_SOLD = 'sold';

    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_IN_USE,
        self::STATUS_MAINTENANCE,
        self::STATUS_OUT_OF_SERVICE,
        self::STATUS_RENTAL_ACTIVE,
        self::STATUS_CLIENT_USE,
        self::STATUS_RETIRED,
        self::STATUS_SOLD,
    ];

    protected static function booted(): void
    {
        static::creating(function (Vehicle $vehicle) {
            if (empty($vehicle->fleet_code)) {
                $prefix = strtoupper(substr($vehicle->type ?? 'VEH', 0, 3));
                $count = static::withTrashed()->count() + 1;
                $vehicle->fleet_code = sprintf('%s-%04d', $prefix, $count);
            }
        });
    }

    protected $fillable = [
        'fleet_code',
        'plate_number',
        'label',
        'make',
        'model',
        'year',
        'vin',
        'registration_number',
        'registration_date',
        'color',
        'type',
        'vehicle_class',
        'capacity_kg',
        'capacity_m3',
        'seats',
        'fuel_type',
        'fuel_tank_capacity',
        'battery_capacity_kwh',
        'ownership_type',
        'current_responsible_employee_id',
        'custody_type',
        'current_site_id',
        'current_location_notes',
        'rental_provider_id',
        'rental_start_date',
        'rental_end_date',
        'rental_contract_reference',
        'rental_daily_rate',
        'rental_currency',
        'rental_return_date',
        'rental_status',
        'client_id',
        'client_supplies_driver',
        'client_usage_start',
        'client_usage_end',
        'client_vehicle_status',
        'odometer_km',
        'odometer_last_updated',
        'tracker_device_id',
        'tracker_imei',
        'tracker_provider',
        'tracker_installed_at',
        'last_gps_latitude',
        'last_gps_longitude',
        'last_gps_timestamp',
        'last_location_address',
        'last_speed_kmh',
        'last_inspection_date',
        'next_inspection_date',
        'insurance_expiry_date',
        'registration_expiry_date',
        'status',
        'notes',
        'default_driver_id',
        'last_service_date',
        'next_service_date',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'capacity_kg' => 'decimal:2',
            'capacity_m3' => 'decimal:2',
            'fuel_tank_capacity' => 'decimal:2',
            'battery_capacity_kwh' => 'decimal:2',
            'rental_daily_rate' => 'decimal:3',
            'client_supplies_driver' => 'boolean',
            'registration_date' => 'date',
            'rental_start_date' => 'date',
            'rental_end_date' => 'date',
            'rental_return_date' => 'date',
            'client_usage_start' => 'date',
            'client_usage_end' => 'date',
            'odometer_last_updated' => 'date',
            'tracker_installed_at' => 'date',
            'last_gps_latitude' => 'float',
            'last_gps_longitude' => 'float',
            'last_speed_kmh' => 'float',
            'last_gps_timestamp' => 'datetime',
            'last_service_date' => 'date',
            'next_service_date' => 'date',
            'last_inspection_date' => 'date',
            'next_inspection_date' => 'date',
            'insurance_expiry_date' => 'date',
            'registration_expiry_date' => 'date',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function defaultDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_driver_id');
    }

    public function currentResponsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_responsible_employee_id');
    }

    public function currentSite(): BelongsTo
    {
        return $this->belongsTo(WarehouseSite::class, 'current_site_id');
    }

    public function rentalProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'rental_provider_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(VehicleAssignment::class, 'vehicle_id')
            ->where('is_current', true)
            ->latestOfMany('start_date');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class, 'vehicle_id')
            ->orderByDesc('start_date');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class, 'vehicle_id');
    }

    public function fuelRecords(): HasMany
    {
        return $this->hasMany(VehicleFuelRecord::class, 'vehicle_id')
            ->orderByDesc('fill_date');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(VehicleMaintenanceRecord::class, 'vehicle_id')
            ->orderByDesc('scheduled_date');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(VehicleChecklist::class, 'vehicle_id')
            ->orderByDesc('completed_at');
    }

    public function activityLog(): HasMany
    {
        return $this->hasMany(VehicleActivityLog::class, 'vehicle_id')
            ->orderByDesc('event_date');
    }

    public function mobileWarehouses(): HasMany
    {
        return $this->hasMany(WarehouseSite::class, 'vehicle_id')
            ->where('site_type', 'mobile');
    }

    public function workshopUnits(): HasMany
    {
        return $this->hasMany(WorkshopUnit::class, 'vehicle_id');
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

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeCompanyOwned($query)
    {
        return $query->where('ownership_type', 'company');
    }

    public function scopeRental($query)
    {
        return $query->where('ownership_type', 'rental');
    }

    public function scopeClientVehicles($query)
    {
        return $query->where('ownership_type', 'client');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['available', 'in_use', 'maintenance']);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByFuelType($query, string $fuelType)
    {
        return $query->where('fuel_type', $fuelType);
    }

    public function scopeRequiresMaintenance($query)
    {
        return $query->where('next_service_date', '<=', now()->addDays(7))
            ->orWhere('next_inspection_date', '<=', now()->addDays(7));
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        $threshold = now()->addDays($days);

        return $query->where(function ($q) use ($threshold) {
            $q->where('insurance_expiry_date', '<=', $threshold)
                ->orWhere('registration_expiry_date', '<=', $threshold);
        });
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([$this->make, $this->model, $this->year]);

        return implode(' ', $parts) ?: $this->label;
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->status === 'available';
    }

    public function getIsCompanyOwnedAttribute(): bool
    {
        return $this->ownership_type === 'company';
    }

    public function getIsRentalAttribute(): bool
    {
        return $this->ownership_type === 'rental';
    }

    public function getIsClientVehicleAttribute(): bool
    {
        return $this->ownership_type === 'client';
    }
}
