<?php

namespace App\Modules\Logistics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryProviderActivityLog extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'delivery_provider_id',
        'event_type',
        'event_title',
        'event_description',
        'event_date',
        'old_value',
        'new_value',
        'metadata',
        'dwo_id',
        'impact',
        'score_change',
        'notes',
        'recorded_by',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'datetime',
            'metadata' => 'array',
            'score_change' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function deliveryProvider(): BelongsTo
    {
        return $this->belongsTo(DeliveryServiceProvider::class, 'delivery_provider_id');
    }

    public function dwo(): BelongsTo
    {
        return $this->belongsTo(DeliveryWorkOrder::class, 'dwo_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeByProvider($query, string $providerId)
    {
        return $query->where('delivery_provider_id', $providerId);
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopePositiveImpact($query)
    {
        return $query->where('impact', 'positive');
    }

    public function scopeNegativeImpact($query)
    {
        return $query->where('impact', 'negative');
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('event_date', [$startDate, $endDate]);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('event_date', '>=', now()->subDays($days));
    }
}
