<?php

namespace App\Modules\Procurement\Models;

use App\Modules\BusinessPartners\Models\Supplier;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $rfq_id
 * @property string $supplier_id
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $viewed_at
 * @property string $status
 * @property string|null $decline_reason
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read RfqHeader $rfq
 * @property-read Supplier $supplier
 * @property-read RfqResponse|null $response
 */
class RfqInvitation extends Model
{
    use HasUlids;

    protected $fillable = [
        'rfq_id',
        'supplier_id',
        'sent_at',
        'viewed_at',
        'status',
        'decline_reason',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RfqHeader::class, 'rfq_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function response(): HasOne
    {
        return $this->hasOne(RfqResponse::class, 'rfq_id', 'rfq_id')
            ->where('supplier_id', $this->supplier_id);
    }
}
