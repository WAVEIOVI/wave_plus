<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Modules\OperationalHub\Models\ChecklistTemplate;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Records a completed inspection / pre-/post-trip checklist for a vehicle.
 */
class VehicleChecklist extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'vehicle_id',
        'checklist_template_id',
        'checklist_type',
        'title',
        'completed_at',
        'completed_by_employee_id',
        'driver_id',
        'odometer_km',
        'location',
        'checklist_items',
        'overall_status',
        'items_passed',
        'items_failed',
        'items_total',
        'has_issues',
        'issues_found',
        'notes',
        'recommendations',
        'requires_maintenance',
        'maintenance_record_id',
        'mission_id',
        'dwo_id',
        'swo_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'checklist_items' => 'array',
            'issues_found' => 'array',
            'has_issues' => 'boolean',
            'requires_maintenance' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function checklistTemplate(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function completedByEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_employee_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(VehicleMaintenanceRecord::class, 'maintenance_record_id');
    }

    public function dwo(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Logistics\Models\DeliveryWorkOrder::class, 'dwo_id');
    }

    public function swo(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkOrder::class, 'swo_id');
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

    public function scopeByType($query, string $type)
    {
        return $query->where('checklist_type', $type);
    }

    public function scopePassed($query)
    {
        return $query->where('overall_status', 'passed');
    }

    public function scopeFailed($query)
    {
        return $query->where('overall_status', 'failed');
    }

    public function scopeWithIssues($query)
    {
        return $query->where('has_issues', true);
    }

    public function scopeRequiresMaintenance($query)
    {
        return $query->where('requires_maintenance', true);
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getPassRateAttribute(): ?float
    {
        return $this->items_total > 0
            ? round(($this->items_passed / $this->items_total) * 100, 2)
            : null;
    }
}
