<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\OperationalHub\Models\WorkshopUnit;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Records a scheduled or completed maintenance event for a fleet vehicle.
 */
class VehicleMaintenanceRecord extends Model
{
    use GeneratesReference, SoftDeletes;
    use HasUlids;

    protected $fillable = [
        'maintenance_number',
        'vehicle_id',
        'maintenance_type',
        'priority',
        'scheduled_date',
        'completed_date',
        'odometer_km',
        'workshop_provider_id',
        'workshop_unit_id',
        'workshop_name',
        'title',
        'description',
        'work_items',
        'parts_used',
        'labor_cost',
        'parts_cost',
        'total_cost',
        'currency',
        'invoice_number',
        'invoice_date',
        'next_service_date',
        'next_service_km',
        'status',
        'notes',
        'documents',
        'scheduled_by',
        'performed_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'completed_date' => 'date',
            'invoice_date' => 'date',
            'next_service_date' => 'date',
            'labor_cost' => 'decimal:3',
            'parts_cost' => 'decimal:3',
            'total_cost' => 'decimal:3',
            'work_items' => 'array',
            'parts_used' => 'array',
            'documents' => 'array',
        ];
    }

    // ─── Reference Generation ────────────────────────────────────

    protected function getReferenceColumn(): string
    {
        return 'maintenance_number';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('VM-%05d', $nextValue);
    }

    protected function getDocumentSequenceName(): string
    {
        return 'VM';
    }

    // ─── Relationships ───────────────────────────────────────────

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function workshopProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'workshop_provider_id');
    }

    public function workshopUnit(): BelongsTo
    {
        return $this->belongsTo(WorkshopUnit::class, 'workshop_unit_id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
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

    public function scopePreventive($query)
    {
        return $query->where('maintenance_type', 'preventive');
    }

    public function scopeCorrective($query)
    {
        return $query->where('maintenance_type', 'corrective');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeUpcoming($query, int $days = 30)
    {
        return $query->where('scheduled_date', '<=', now()->addDays($days))
            ->where('status', 'scheduled');
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status !== 'scheduled' || ! $this->scheduled_date) {
            return false;
        }

        return \Illuminate\Support\Carbon::parse($this->scheduled_date)->isPast();
    }
}
