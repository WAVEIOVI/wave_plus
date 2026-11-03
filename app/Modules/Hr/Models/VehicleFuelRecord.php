<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Records each fuel fill event for a fleet vehicle.
 */
class VehicleFuelRecord extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'vehicle_id',
        'fill_date',
        'fill_time',
        'fuel_type',
        'quantity',
        'unit',
        'odometer_km',
        'unit_price',
        'total_cost',
        'currency',
        'supplier',
        'station',
        'city',
        'receipt_number',
        'card_last_four',
        'payment_method',
        'filled_by_employee_id',
        'driver_id',
        'is_full_tank',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fill_date' => 'date',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'total_cost' => 'decimal:3',
            'is_full_tank' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function filledByEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filled_by_employee_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
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

    public function scopeByVehicle($query, string $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeByFuelType($query, string $fuelType)
    {
        return $query->where('fuel_type', $fuelType);
    }

    public function scopeFullTanks($query)
    {
        return $query->where('is_full_tank', true);
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('fill_date', [$startDate, $endDate]);
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getCostPerUnitAttribute(): ?float
    {
        return $this->total_cost && $this->quantity
            ? round($this->total_cost / $this->quantity, 3)
            : null;
    }
}
