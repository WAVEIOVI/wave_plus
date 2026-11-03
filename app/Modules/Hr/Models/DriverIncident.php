<?php

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverIncident extends Model
{
    use GeneratesReference, SoftDeletes;
    use HasUlids;

    protected $fillable = [
        'incident_number',
        'driver_id',
        'employee_id',
        'vehicle_id',
        'incident_type',
        'severity',
        'incident_date',
        'location',
        'city',
        'address',
        'title',
        'description',
        'incident_details',
        'property_damage',
        'vehicle_damage',
        'personal_injury',
        'third_party_involved',
        'damage_description',
        'estimated_cost',
        'actual_cost',
        'currency',
        'police_report_filed',
        'police_report_number',
        'insurance_claim_filed',
        'insurance_claim_number',
        'insurance_claim_status',
        'driver_at_fault',
        'alcohol_test_conducted',
        'drug_test_conducted',
        'driver_statement',
        'witnesses',
        'documents',
        'status',
        'requires_driver_training',
        'driver_suspended',
        'suspension_until',
        'corrective_actions',
        'mission_id',
        'dwo_id',
        'swo_id',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'reported_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'datetime',
            'suspension_until' => 'date',
            'resolved_at' => 'datetime',
            'property_damage' => 'boolean',
            'vehicle_damage' => 'boolean',
            'personal_injury' => 'boolean',
            'third_party_involved' => 'boolean',
            'police_report_filed' => 'boolean',
            'insurance_claim_filed' => 'boolean',
            'alcohol_test_conducted' => 'boolean',
            'drug_test_conducted' => 'boolean',
            'requires_driver_training' => 'boolean',
            'driver_suspended' => 'boolean',
            'estimated_cost' => 'decimal:3',
            'actual_cost' => 'decimal:3',
            'incident_details' => 'array',
            'witnesses' => 'array',
            'documents' => 'array',
        ];
    }

    // ─── Reference Generation ────────────────────────────────────

    protected function getReferenceColumn(): string
    {
        return 'incident_number';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('INC-%05d', $nextValue);
    }

    protected function getDocumentSequenceName(): string
    {
        return 'INC';
    }

    // ─── Relationships ───────────────────────────────────────────

    public function driver(): BelongsTo
    {
        return $this->belongsTo(ApprovedDriver::class, 'driver_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function dwo(): BelongsTo
    {
        return $this->belongsTo(DeliveryWorkOrder::class, 'dwo_id');
    }

    public function swo(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkOrder::class, 'swo_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
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

    public function scopeByDriver($query, string $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeByVehicle($query, string $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['reported', 'under_investigation', 'pending_action']);
    }

    public function scopeResolved($query)
    {
        return $query->whereIn('status', ['resolved', 'closed']);
    }

    public function scopeDriverAtFault($query)
    {
        return $query->where('driver_at_fault', 'yes');
    }

    public function scopeWithDamage($query)
    {
        return $query->where(function ($q) {
            $q->where('property_damage', true)
                ->orWhere('vehicle_damage', true)
                ->orWhere('personal_injury', true);
        });
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getIsResolvedAttribute(): bool
    {
        return in_array($this->status, ['resolved', 'closed']);
    }

    public function getIsCriticalAttribute(): bool
    {
        return $this->severity === 'critical'
            || $this->personal_injury
            || $this->driver_suspended;
    }
}
