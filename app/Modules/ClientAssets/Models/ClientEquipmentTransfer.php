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
 * @property string|null $from_site_id
 * @property string|null $from_area_id
 * @property string|null $to_site_id
 * @property string|null $to_area_id
 * @property int|null $transferred_by
 * @property \Illuminate\Support\Carbon $transfer_date
 * @property string|null $reason
 * @property string|null $condition_at_transfer
 * @property string|null $notes
 * @property-read \App\Modules\ClientAssets\Models\ClientEquipment|null $equipment
 * @property-read \App\Modules\ClientAssets\Models\ClientSite|null $fromSite
 * @property-read \App\Modules\ClientAssets\Models\ClientSiteArea|null $fromArea
 * @property-read \App\Modules\ClientAssets\Models\ClientSite|null $toSite
 * @property-read \App\Modules\ClientAssets\Models\ClientSiteArea|null $toArea
 * @property-read \App\Models\User|null $transferredByUser
 */
class ClientEquipmentTransfer extends Model
{
    use HasUlids;

    protected $table = 'client_equipment_transfers';

    protected $fillable = [
        'equipment_id',
        'from_site_id',
        'from_area_id',
        'to_site_id',
        'to_area_id',
        'transferred_by',
        'transfer_date',
        'reason',
        'condition_at_transfer',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClientEquipment::class, 'equipment_id');
    }

    public function fromSite(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'from_site_id');
    }

    public function fromArea(): BelongsTo
    {
        return $this->belongsTo(ClientSiteArea::class, 'from_area_id');
    }

    public function toSite(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'to_site_id');
    }

    public function toArea(): BelongsTo
    {
        return $this->belongsTo(ClientSiteArea::class, 'to_area_id');
    }

    public function transferredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
