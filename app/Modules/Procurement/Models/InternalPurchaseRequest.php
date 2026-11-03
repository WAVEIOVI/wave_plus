<?php

namespace App\Modules\Procurement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $reference
 * @property string $trigger_source
 * @property string|null $trigger_reference
 * @property string $requested_by
 * @property int|null $assigned_to
 * @property string $urgency
 * @property \Illuminate\Support\Carbon|null $needed_by_date
 * @property string $status
 * @property string|null $merged_into_ipr_id
 * @property string|null $converted_to_po_id
 * @property string|null $approval_note
 * @property string|null $rejection_reason
 * @property string|null $internal_justification
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|IprLine[] $lines
 * @property-read User $requestedBy
 * @property-read User|null $assignedTo
 * @property-read InternalPurchaseRequest|null $mergedInto
 * @property-read PurchaseOrder|null $po
 */
class InternalPurchaseRequest extends Model
{
    use \App\Traits\GeneratesReference, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'trigger_source',
        'trigger_reference',
        'requested_by',
        'assigned_to',
        'urgency',
        'needed_by_date',
        'status',
        'merged_into_ipr_id',
        'converted_to_po_id',
        'approval_note',
        'rejection_reason',
        'internal_justification',
    ];

    protected function casts(): array
    {
        return [
            'needed_by_date' => 'date',
        ];
    }

    protected function getDocumentSequenceName(): string
    {
        return 'IPR-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%03d', $sequenceType, $nextValue);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(IprLine::class, 'ipr_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_ipr_id');
    }

    public function po(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'converted_to_po_id');
    }
}
