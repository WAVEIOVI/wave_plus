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
 * @property string $creation_path
 * @property string|null $rfq_id
 * @property array|null $ipr_ids
 * @property string $supplier_id
 * @property int|null $supplier_contact_id
 * @property string $currency
 * @property float $exchange_rate
 * @property string|null $payment_terms
 * @property string|null $incoterms
 * @property string|null $delivery_address
 * @property \Illuminate\Support\Carbon|null $committed_delivery_date
 * @property float $total_ht
 * @property float $total_tax
 * @property float $total_ttc
 * @property bool $approval_required
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 * @property string|null $supplier_po_reference
 * @property string|null $notes_to_supplier
 * @property string|null $internal_notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|PoLine[] $lines
 * @property-read \Illuminate\Database\Eloquent\Collection|GoodsReceiptNote[] $goodsReceiptNotes
 * @property-read Supplier $supplier
 * @property-read User|null $approvedBy
 */
class PurchaseOrder extends Model
{
    use \App\Traits\GeneratesReference, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'creation_path',
        'rfq_id',
        'ipr_ids',
        'supplier_id',
        'site_id',
        'supplier_contact_id',
        'supplier_address_id',
        'currency',
        'exchange_rate',
        'payment_terms',
        'incoterms',
        'delivery_address',
        'committed_delivery_date',
        'total_ht',
        'total_tax',
        'total_ttc',
        'approval_required',
        'approved_by',
        'approved_at',
        'status',
        'sent_at',
        'acknowledged_at',
        'supplier_po_reference',
        'notes_to_supplier',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'ipr_ids' => 'array',
            'exchange_rate' => 'decimal:6',
            'committed_delivery_date' => 'date',
            'total_ht' => 'decimal:3',
            'total_tax' => 'decimal:3',
            'total_ttc' => 'decimal:3',
            'approval_required' => 'boolean',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    protected function getDocumentSequenceName(): string
    {
        return 'PO-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%03d', $sequenceType, $nextValue);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PoLine::class, 'po_id');
    }

    public function goodsReceiptNotes(): HasMany
    {
        return $this->hasMany(GoodsReceiptNote::class, 'po_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\WarehouseSite::class, 'site_id');
    }
}
