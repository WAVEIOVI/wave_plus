<?php

namespace App\Modules\Procurement\Models;

use App\Modules\BusinessPartners\Models\Supplier;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $rfq_id
 * @property string $supplier_id
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $validity_until
 * @property \Illuminate\Support\Carbon|null $can_deliver_by
 * @property string|null $payment_terms_offered
 * @property float $total_ht
 * @property float|null $evaluation_score
 * @property array|null $evaluation_details
 * @property bool $is_awarded
 * @property string|null $supplier_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read RfqHeader $rfq
 * @property-read Supplier $supplier
 * @property-read \Illuminate\Database\Eloquent\Collection|RfqResponseLine[] $lines
 */
class RfqResponse extends Model
{
    use HasUlids;

    protected $fillable = [
        'rfq_id',
        'supplier_id',
        'submitted_at',
        'validity_until',
        'can_deliver_by',
        'payment_terms_offered',
        'total_ht',
        'evaluation_score',
        'evaluation_details',
        'is_awarded',
        'supplier_notes',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'validity_until' => 'date',
            'can_deliver_by' => 'date',
            'total_ht' => 'decimal:3',
            'evaluation_score' => 'decimal:2',
            'evaluation_details' => 'array',
            'is_awarded' => 'boolean',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RfqHeader::class, 'rfq_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function responseLines(): HasMany
    {
        return $this->hasMany(RfqResponseLine::class, 'rfq_response_id');
    }
}
