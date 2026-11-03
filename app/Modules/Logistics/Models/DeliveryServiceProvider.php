<?php

namespace App\Modules\Logistics\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryServiceProvider extends Model
{
    use GeneratesReference, SoftDeletes;
    use HasUlids;

    protected $fillable = [
        'provider_code',
        'service_provider_id',
        'operational_status',
        'service_areas',
        'delivery_modes',
        'vehicle_types',
        'can_handle_fragile',
        'can_handle_hazmat',
        'can_handle_refrigerated',
        'max_weight_kg',
        'max_volume_m3',
        'contract_reference',
        'contract_start_date',
        'contract_end_date',
        'pricing_model',
        'rate_card',
        'currency',
        'total_deliveries',
        'successful_deliveries',
        'failed_deliveries',
        'delayed_deliveries',
        'success_rate',
        'on_time_rate',
        'average_delivery_time_hours',
        'performance_score',
        'sla_terms',
        'daily_capacity',
        'current_load',
        'accepts_new_orders',
        'operations_contact_name',
        'operations_phone',
        'operations_email',
        'dispatch_phone',
        'emergency_phone',
        'insurance_details',
        'certifications',
        'documents',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'service_areas' => 'array',
            'delivery_modes' => 'array',
            'vehicle_types' => 'array',
            'can_handle_fragile' => 'boolean',
            'can_handle_hazmat' => 'boolean',
            'can_handle_refrigerated' => 'boolean',
            'max_weight_kg' => 'decimal:2',
            'max_volume_m3' => 'decimal:2',
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'rate_card' => 'array',
            'success_rate' => 'decimal:2',
            'on_time_rate' => 'decimal:2',
            'average_delivery_time_hours' => 'decimal:2',
            'performance_score' => 'decimal:2',
            'sla_terms' => 'array',
            'accepts_new_orders' => 'boolean',
            'insurance_details' => 'array',
            'certifications' => 'array',
            'documents' => 'array',
            'metadata' => 'array',
        ];
    }

    // ─── Reference Generation ────────────────────────────────────

    protected function getReferenceColumn(): string
    {
        return 'provider_code';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('DP-%05d', $nextValue);
    }

    protected function getDocumentSequenceName(): string
    {
        return 'DP';
    }

    // ─── Relationships ───────────────────────────────────────────

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }

    public function activityLog(): HasMany
    {
        return $this->hasMany(DeliveryProviderActivityLog::class, 'delivery_provider_id')
            ->orderByDesc('event_date');
    }

    public function dwos(): HasMany
    {
        return $this->hasMany(DeliveryWorkOrder::class, 'delivery_provider_id');
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

    public function scopeActive($query)
    {
        return $query->where('operational_status', 'active');
    }

    public function scopeAcceptingOrders($query)
    {
        return $query->where('operational_status', 'active')
            ->where('accepts_new_orders', true);
    }

    public function scopeAtCapacity($query)
    {
        return $query->whereColumn('current_load', '>=', 'daily_capacity');
    }

    public function scopeCanHandleFragile($query)
    {
        return $query->where('can_handle_fragile', true);
    }

    public function scopeCanHandleHazmat($query)
    {
        return $query->where('can_handle_hazmat', true);
    }

    public function scopeCanHandleRefrigerated($query)
    {
        return $query->where('can_handle_refrigerated', true);
    }

    public function scopeServesArea($query, string $area)
    {
        return $query->whereJsonContains('service_areas', $area);
    }

    public function scopeHighPerformance($query, float $minScore = 80.0)
    {
        return $query->where('performance_score', '>=', $minScore);
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getIsActiveAttribute(): bool
    {
        return $this->operational_status === 'active';
    }

    public function getIsAcceptingOrdersAttribute(): bool
    {
        return $this->is_active && $this->accepts_new_orders;
    }

    public function getCapacityUtilizationAttribute(): ?float
    {
        return $this->daily_capacity > 0
            ? round(($this->current_load / $this->daily_capacity) * 100, 2)
            : null;
    }

    public function getAvailableCapacityAttribute(): int
    {
        return max(0, $this->daily_capacity - $this->current_load);
    }

    public function canHandleWeight(float $weightKg): bool
    {
        return ! $this->max_weight_kg || $weightKg <= $this->max_weight_kg;
    }

    public function canHandleVolume(float $volumeM3): bool
    {
        return ! $this->max_volume_m3 || $volumeM3 <= $this->max_volume_m3;
    }

    public function servesArea(string $area): bool
    {
        return in_array($area, $this->service_areas ?? []);
    }
}
