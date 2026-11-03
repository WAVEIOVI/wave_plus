<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $equipment_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property string|null $reason
 * @property string|null $notes
 * @property int|null $changed_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Modules\ClientAssets\Models\ClientEquipment|null $equipment
 * @property-read \App\Models\User|null $changer
 */
class EquipmentStatusHistory extends Model
{
    use HasUlids;

    protected $table = 'equipment_status_histories';

    protected $fillable = [
        'equipment_id',
        'status',
        'started_at',
        'ended_at',
        'reason',
        'notes',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClientEquipment::class, 'equipment_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeCurrent($query)
    {
        return $query->whereNull('ended_at');
    }
}
