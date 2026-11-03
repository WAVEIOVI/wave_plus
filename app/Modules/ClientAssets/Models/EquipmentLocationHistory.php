<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $equipment_id
 * @property string $client_id
 * @property string|null $site_id
 * @property string|null $area_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property string $movement_type
 * @property string|null $reason
 * @property string|null $notes
 * @property int|null $changed_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Modules\ClientAssets\Models\ClientEquipment|null $equipment
 * @property-read \App\Modules\BusinessPartners\Models\Client|null $client
 * @property-read \App\Modules\ClientAssets\Models\ClientSite|null $site
 * @property-read \App\Modules\ClientAssets\Models\ClientSiteArea|null $area
 * @property-read \App\Models\User|null $changer
 */
class EquipmentLocationHistory extends Model
{
    use HasUlids;

    protected $table = 'equipment_location_histories';

    protected $fillable = [
        'equipment_id',
        'client_id',
        'site_id',
        'area_id',
        'started_at',
        'ended_at',
        'movement_type',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'site_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(ClientSiteArea::class, 'area_id');
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
