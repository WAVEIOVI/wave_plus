<?php

namespace App\Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $rfq_response_id
 * @property string $rfq_line_id
 * @property float $offered_unit_price_ht
 * @property float $offered_qty
 * @property int $offered_lead_time_days
 * @property bool $certifications_confirmed
 * @property float|null $vs_target_pct
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read RfqResponse $rfqResponse
 * @property-read RfqLine $rfqLine
 */
class RfqResponseLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'rfq_response_id',
        'rfq_line_id',
        'offered_unit_price_ht',
        'offered_qty',
        'offered_lead_time_days',
        'certifications_confirmed',
        'vs_target_pct',
    ];

    protected function casts(): array
    {
        return [
            'offered_unit_price_ht' => 'decimal:3',
            'offered_qty' => 'decimal:3',
            'certifications_confirmed' => 'array',
            'vs_target_pct' => 'decimal:2',
        ];
    }

    public function rfqResponse(): BelongsTo
    {
        return $this->belongsTo(RfqResponse::class, 'rfq_response_id');
    }

    public function rfqLine(): BelongsTo
    {
        return $this->belongsTo(RfqLine::class, 'rfq_line_id');
    }
}
