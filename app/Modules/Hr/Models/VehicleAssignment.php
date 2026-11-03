<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Modules\Inventory\Models\WarehouseSite;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Records each custody/location assignment period for a fleet vehicle.
 */
class VehicleAssignment extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'vehicle_id',
        'responsible_employee_id',
        'custody_type',
        'site_id',
        'location_notes',
        'start_date',
        'end_date',
        'is_current',
        'reason',
        'notes',
        'odometer_at_start',
        'odometer_at_end',
        'assigned_by',
        'returned_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_employee_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(WarehouseSite::class, 'site_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
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

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('end_date')
            ->orWhere('is_current', true);
    }

    public function scopeByEmployee($query, string $employeeId)
    {
        return $query->where('responsible_employee_id', $employeeId);
    }

    public function scopeByVehicle($query, string $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }
}
