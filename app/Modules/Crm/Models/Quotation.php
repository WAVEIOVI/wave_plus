<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Crm\Enums\PaymentTerms;
use App\Modules\Crm\Enums\QuotationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $reference
 * @property int $version
 * @property string|null $parent_quotation_id
 * @property string|null $pr_id
 * @property string $client_id
 * @property string|null $site_id
 * @property string|null $contract_id
 * @property \Illuminate\Support\Carbon $valid_until
 * @property string $currency
 * @property PaymentTerms|null $payment_terms
 * @property float|null $global_discount_pct
 * @property float $total_ht
 * @property float $total_tva
 * @property float $total_ttc
 * @property bool $approval_required
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property string|null $converted_to_so_id
 * @property string|null $rejection_reason
 * @property string|null $cover_letter
 * @property QuotationStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Client $client
 * @property-read Quotation|null $parentQuotation
 * @property-read \Illuminate\Database\Eloquent\Collection|Quotation[] $revisions
 * @property-read PriceRequest|null $priceRequest
 * @property-read Contract|null $contract
 * @property-read User|null $approvedByUser
 * @property-read SalesOrder|null $convertedToSalesOrder
 * @property-read \Illuminate\Database\Eloquent\Collection|QuotationLine[] $lines
 */
class Quotation extends Model
{
    use \App\Traits\GeneratesReference, HasUlids, SoftDeletes;

    protected $fillable = [
        'reference',
        'version',
        'parent_quotation_id',
        'pr_id',
        'client_id',
        'site_id',
        'contract_id',
        'valid_until',
        'currency',
        'payment_terms',
        'global_discount_pct',
        'total_ht',
        'total_tva',
        'total_ttc',
        'approval_required',
        'approved_by',
        'approved_at',
        'sent_at',
        'accepted_at',
        'converted_to_so_id',
        'rejection_reason',
        'cover_letter',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'payment_terms' => PaymentTerms::class,
            'version' => 'integer',
            'valid_until' => 'date',
            'global_discount_pct' => 'decimal:2',
            'total_ht' => 'decimal:3',
            'total_tva' => 'decimal:3',
            'total_ttc' => 'decimal:3',
            'approval_required' => 'boolean',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected function getDocumentSequenceName(): string
    {
        return 'QT-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    // ─── Relationships ───────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function parentQuotation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_quotation_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_quotation_id');
    }

    public function priceRequest(): BelongsTo
    {
        return $this->belongsTo(PriceRequest::class, 'pr_id');
    }

    public function createRevision(): self
    {
        $newQuotation = $this->replicate(['reference', 'approved_by', 'approved_at', 'sent_at', 'accepted_at', 'converted_to_so_id', 'rejection_reason', 'created_at', 'updated_at', 'deleted_at']);
        $newQuotation->version = $this->version + 1;
        $newQuotation->parent_quotation_id = $this->id;
        $newQuotation->status = QuotationStatus::Draft->value;
        $newQuotation->save();

        foreach ($this->lines as $line) {
            $newLine = $line->replicate(['created_at', 'updated_at']);
            $newLine->quotation_id = $newQuotation->id;
            $newLine->save();
        }

        return $newQuotation;
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function convertedToSalesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_to_so_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class);
    }
}
