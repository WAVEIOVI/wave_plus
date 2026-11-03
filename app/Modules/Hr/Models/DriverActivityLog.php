<?php

namespace App\Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DriverActivityLog extends Model
{
    use HasUlids;

    protected $table = 'driver_activity_log';

    public $timestamps = false;

    protected $fillable = [
        'driver_id',
        'employee_id',
        'event_type',
        'event_title',
        'event_description',
        'event_date',
        'old_value',
        'new_value',
        'metadata',
        'vehicle_id',
        'related_id',
        'related_type',
        'impact',
        'score_change',
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

    public function related(): MorphTo
    {
        return $this->morphTo();
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

    public function scopeByDriver($query, string $driverId)
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeByEmployee($query, string $employeeId)
    {
        return $query->where('employee_id', $employeeId);
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
