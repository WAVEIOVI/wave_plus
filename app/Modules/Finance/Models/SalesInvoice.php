<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInvoice extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'type',
        'client_id',
        'financial_period_id',
        'invoice_date',
        'due_date',
        'source_type',
        'source_id',
        'source_reference',
        'related_invoice_id',
        'currency',
        'subtotal_ht',
        'tax_amount',
        'total_ttc',
        'amount_paid',
        'amount_outstanding',
        'status',
        'payment_status',
        'posted_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'payment_terms',
        'cost_center_id',
        'internal_notes',
        'client_notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'subtotal_ht' => 'decimal:3',
            'tax_amount' => 'decimal:3',
            'total_ttc' => 'decimal:3',
            'amount_paid' => 'decimal:3',
            'amount_outstanding' => 'decimal:3',
        ];
    }

    // ─── Relationships ──────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class, 'sales_invoice_id')
            ->orderBy('line_number');
    }

    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'related_invoice_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'invoice_id')
            ->where('invoice_type', 'sales_invoice');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ─────────────────────────────────────────

    public function scopePosted($query)
    {
        return $query->where('status', '!=', 'draft')
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'void');
    }

    public function scopeOverdue($query)
    {
        return $query->where('payment_status', '!=', 'paid')
            ->where('due_date', '<', now()->toDateString());
    }

    public function scopeForClient($query, string $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    // ─── Accessors ────────────────────────────────────────

    public function getIsOverdueAttribute(): bool
    {
        return $this->payment_status !== 'paid'
            && $this->due_date
            && $this->due_date->isPast();
    }

    public function getDaysOverdueAttribute(): int
    {
        if (! $this->is_overdue) {
            return 0;
        }

        return (int) $this->due_date->diffInDays(now());
    }

    // ─── Helpers ─────────────────────────────────────────

    public function recalculateBalance(): void
    {
        $paid = $this->payments()->sum('allocated_amount');
        $this->amount_paid = $paid;
        $this->amount_outstanding = max(0, $this->total_ttc - $paid);
        $this->payment_status = match (true) {
            $this->amount_outstanding <= 0 => 'paid',
            $paid > 0 => 'partial',
            default => 'unpaid',
        };
        $this->save();
    }
}
