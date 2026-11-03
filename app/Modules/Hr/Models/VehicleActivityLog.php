<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only log of meaningful fleet events against a vehicle.
 * System-generated on status changes, assignments, maintenance etc.
 */
class VehicleActivityLog extends Model
{
    use HasUlids;

    protected $table = 'vehicle_activity_log';

    public $timestamps = false;

    protected $fillable = [
        'vehicle_id',
        'event_type',
        'event_title',
        'event_description',
        'event_date',
        'old_value',
        'new_value',
        'metadata',
        'related_id',
        'related_type',
        'actor_employee_id',
        'actor_name',
        'location',
        'odometer_km',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function actorEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_employee_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeByVehicle($query, string $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
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
