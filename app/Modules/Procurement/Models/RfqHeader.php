<?php

namespace App\Modules\Procurement\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Supplier;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $reference
 * @property array|null $ipr_ids
 * @property string $title
 * @property \Illuminate\Support\Carbon $response_deadline
 * @property \Illuminate\Support\Carbon|null $required_delivery_date
 * @property string|null $evaluation_criteria
 * @property string|null $terms_and_conditions
 * @property string $status
 * @property string $awarded_to_supplier_id
 * @property \Illuminate\Support\Carbon|null $awarded_at
 * @property int|null $awarded_by
 * @property string|null $converted_to_po_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|RfqLine[] $lines
 * @property-read \Illuminate\Database\Eloquent\Collection|RfqInvitation[] $invitations
 * @property-read \Illuminate\Database\Eloquent\Collection|RfqResponse[] $responses
 * @property-read Supplier|null $awardedTo
 * @property-read User|null $awarder
 */
class RfqHeader extends Model
{
    use \App\Traits\GeneratesReference, HasFactory, HasUlids, SoftDeletes;

    protected $table = 'rfq_headers';

    protected $fillable = [
        'ipr_ids',
        'title',
        'response_deadline',
        'required_delivery_date',
        'evaluation_criteria',
        'terms_and_conditions',
        'status',
        'awarded_to_supplier_id',
        'awarded_at',
        'awarded_by',
        'converted_to_po_id',
    ];

    protected function casts(): array
    {
        return [
            'ipr_ids' => 'array',
            'evaluation_criteria' => 'array',
            'response_deadline' => 'datetime',
            'required_delivery_date' => 'date',
            'awarded_at' => 'datetime',
        ];
    }

    protected function getDocumentSequenceName(): string
    {
        return 'RFQ-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%03d', $sequenceType, $nextValue);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RfqLine::class, 'rfq_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(RfqInvitation::class, 'rfq_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(RfqResponse::class, 'rfq_id');
    }

    public function awardedToSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'awarded_to_supplier_id');
    }

    public function awardedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}
