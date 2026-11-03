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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string $reference
 * @property string $po_id
 * @property string $supplier_id
 * @property string $received_by
 * @property \Illuminate\Support\Carbon $received_at
 * @property string|null $supplier_delivery_note_ref
 * @property string|null $supplier_delivery_note_path
 * @property string $status
 * @property bool $is_partial_delivery
 * @property string|null $overall_qc_result
 * @property int|null $qc_performed_by
 * @property \Illuminate\Support\Carbon|null $qc_performed_at
 * @property bool $delivery_on_time
 * @property int|null $days_late
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|GrnLine[] $lines
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read Supplier $supplier
 * @property-read User $receivedBy
 * @property-read User|null $qcPerformedBy
 */
class GoodsReceiptNote extends Model implements HasMedia
{
    use \App\Traits\GeneratesReference, HasFactory, HasUlids, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'po_id',
        'supplier_id',
        'received_by',
        'received_at',
        'supplier_delivery_note_ref',
        'supplier_delivery_note_path',
        'status',
        'is_partial_delivery',
        'overall_qc_result',
        'qc_performed_by',
        'qc_performed_at',
        'delivery_on_time',
        'days_late',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'is_partial_delivery' => 'boolean',
            'qc_performed_at' => 'datetime',
            'delivery_on_time' => 'boolean',
            'days_late' => 'integer',
        ];
    }

    protected function getDocumentSequenceName(): string
    {
        return 'GRN-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%03d', $sequenceType, $nextValue);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('delivery_note')
            ->useDisk('local')
            ->singleFile();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GrnLine::class, 'grn_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function qcPerformedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_performed_by');
    }
}
