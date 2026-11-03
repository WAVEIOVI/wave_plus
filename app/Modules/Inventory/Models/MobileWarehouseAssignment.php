<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Hr\Models\Vehicle;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MobileWarehouseAssignment extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'warehouse_site_id',
        'vehicle_id',
        'assigned_from',
        'assigned_until',
        'is_current',
        'assignment_reason',
        'assignment_notes',
        'stock_transferred',
        'stock_transfer_completed_at',
        'items_transferred',
        'assigned_by',
        'released_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_from' => 'datetime',
            'assigned_until' => 'datetime',
            'is_current' => 'boolean',
            'stock_transferred' => 'boolean',
            'stock_transfer_completed_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function warehouseSite(): BelongsTo
    {
        return $this->belongsTo(WarehouseSite::class, 'warehouse_site_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
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
        return $query->whereNull('assigned_until')
            ->orWhere('is_current', true);
    }

    public function scopeByWarehouse($query, string $warehouseId)
    {
        return $query->where('warehouse_site_id', $warehouseId);
    }

    public function scopeByVehicle($query, string $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeStockTransferred($query)
    {
        return $query->where('stock_transferred', true);
    }

    public function scopePendingStockTransfer($query)
    {
        return $query->where('stock_transferred', false)
            ->where('is_current', true);
    }
}
