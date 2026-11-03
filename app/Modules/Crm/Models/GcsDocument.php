<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Modules\Crm\Enums\DocumentStatus;
use App\Modules\Crm\Enums\PaymentTerms;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $reference
 * @property string $sales_order_id
 * @property string|null $contract_id
 * @property DocumentStatus $status
 * @property \Illuminate\Support\Carbon|null $committed_delivery_date
 * @property array|null $goods_lines
 * @property array|null $delivery_address_snapshot
 * @property float $total_ht
 * @property float $total_tva
 * @property float $total_ttc
 * @property PaymentTerms|null $payment_terms
 * @property string|null $incoterms
 * @property string|null $special_delivery_instructions
 * @property \Illuminate\Support\Carbon|null $client_signed_at
 * @property string|null $client_signatory
 * @property string|null $delivery_note_id
 * @property string|null $document_path
 * @property string|null $signed_document_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read SalesOrder $salesOrder
 * @property-read Contract|null $contract
 */
class GcsDocument extends Model
{
    use \App\Traits\GeneratesReference, HasUlids, SoftDeletes;

    protected $table = 'gcs_documents';

    protected $fillable = [
        'reference',
        'sales_order_id',
        'contract_id',
        'status',
        'committed_delivery_date',
        'goods_lines',
        'delivery_address_snapshot',
        'total_ht',
        'total_tva',
        'total_ttc',
        'payment_terms',
        'incoterms',
        'special_delivery_instructions',
        'client_signed_at',
        'client_signatory',
        'delivery_note_id',
        'document_path',
        'signed_document_path',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'payment_terms' => PaymentTerms::class,
            'committed_delivery_date' => 'date',
            'goods_lines' => 'array',
            'delivery_address_snapshot' => 'array',
            'total_ht' => 'decimal:3',
            'total_tva' => 'decimal:3',
            'total_ttc' => 'decimal:3',
            'client_signed_at' => 'datetime',
        ];
    }

    protected function getDocumentSequenceName(): string
    {
        return 'GCS-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    // ─── Relationships ───────────────────────────────────────────

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
