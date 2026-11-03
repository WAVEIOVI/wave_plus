<?php

namespace App\Modules\Hr\Models;

use App\Models\User;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovedDriver extends Model
{
    use GeneratesReference, SoftDeletes;
    use HasUlids;

    protected $table = 'hr_approved_drivers';

    protected $fillable = [
        'driver_authorization_number',
        'employee_id',
        'authorization_status',
        'authorization_date',
        'expiry_date',
        'review_date',
        'license_number',
        'license_category',
        'license_categories',
        'license_issue_date',
        'license_expiry_date',
        'license_issuer',
        'permitted_vehicle_types',
        'permitted_vehicle_classes',
        'can_drive_rental',
        'can_drive_client_vehicles',
        'restrictions',
        'max_passengers',
        'max_cargo_kg',
        'restriction_notes',
        'last_training_date',
        'next_training_date',
        'certifications',
        'incident_count',
        'violation_count',
        'last_incident_date',
        'driver_score',
        'driver_level',
        'suspended_from',
        'suspended_until',
        'suspension_reason',
        'notes',
        'documents',
        'approved_by',
        'approved_at',
        'reviewed_by',
        'reviewed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'authorization_date' => 'date',
            'expiry_date' => 'date',
            'review_date' => 'date',
            'license_issue_date' => 'date',
            'license_expiry_date' => 'date',
            'last_training_date' => 'date',
            'next_training_date' => 'date',
            'last_incident_date' => 'date',
            'suspended_from' => 'date',
            'suspended_until' => 'date',
            'approved_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'license_categories' => 'array',
            'permitted_vehicle_types' => 'array',
            'permitted_vehicle_classes' => 'array',
            'restrictions' => 'array',
            'certifications' => 'array',
            'documents' => 'array',
            'can_drive_rental' => 'boolean',
            'can_drive_client_vehicles' => 'boolean',
            'driver_score' => 'decimal:2',
        ];
    }

    // ─── Reference Generation ────────────────────────────────────

    protected function getReferenceColumn(): string
    {
        return 'driver_authorization_number';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('DRV-%05d', $nextValue);
    }

    protected function getDocumentSequenceName(): string
    {
        return 'DRV';
    }

    // ─── Relationships ───────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(DriverIncident::class, 'driver_id')
            ->orderByDesc('incident_date');
    }

    public function activityLog(): HasMany
    {
        return $this->hasMany(DriverActivityLog::class, 'driver_id')
            ->orderByDesc('event_date');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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

    public function scopeApproved($query)
    {
        return $query->where('authorization_status', 'approved');
    }

    public function scopeActive($query)
    {
        return $query->where('authorization_status', 'approved')
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('suspended_until')
                    ->orWhere('suspended_until', '<', now());
            });
    }

    public function scopeSuspended($query)
    {
        return $query->where('authorization_status', 'suspended')
            ->orWhere(function ($q) {
                $q->where('suspended_until', '>=', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where('authorization_status', 'expired')
            ->orWhere(function ($q) {
                $q->where('expiry_date', '<', now());
            });
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>=', now())
            ->where('authorization_status', 'approved');
    }

    public function scopeCanDriveVehicleType($query, string $type)
    {
        return $query->whereJsonContains('permitted_vehicle_types', $type);
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getIsActiveAttribute(): bool
    {
        return $this->authorization_status === 'approved'
            && (! $this->expiry_date || $this->expiry_date->isFuture())
            && (! $this->suspended_until || $this->suspended_until->isPast());
    }

    public function getIsSuspendedAttribute(): bool
    {
        return $this->authorization_status === 'suspended'
            || ($this->suspended_until && $this->suspended_until->isFuture());
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->authorization_status === 'expired'
            || ($this->expiry_date && $this->expiry_date->isPast());
    }

    public function canDriveVehicleType(string $type): bool
    {
        return in_array($type, $this->permitted_vehicle_types ?? []);
    }

    public function canDriveVehicleClass(string $class): bool
    {
        return in_array($class, $this->permitted_vehicle_classes ?? []);
    }
}
