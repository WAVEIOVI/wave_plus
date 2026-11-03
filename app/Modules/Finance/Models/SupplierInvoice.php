<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Supplier;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierInvoice extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'supplier_invoice_ref',
        'type',
        'supplier_id',
        'financial_period_id',
        'purchase_order_id',
        'grn_id',
        'invoice_date',
        'due_date',
        'currency',
        'subtotal_ht',
        'tax_amount',
        'total_ttc',
        'amount_paid',
        'amount_outstanding',
        'status',
        'payment_status',
        'matching_status',
        'posted_at',
        'payment_terms',
        'cost_center_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date'       => 'date',
            'due_date'           => 'date',
            'posted_at'          => 'datetime',
            'subtotal_ht'        => 'decimal:3',
            'tax_amount'         => 'decimal:3',
            'total_ttc'          => 'decimal:3',
            'amount_paid'        => 'decimal:3',
            'amount_outstanding' => 'decimal:3',
        ];
    }

    // ─── Relationships ──────────────────────────────────

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class, 'supplier_invoice_id')
            ->orderBy('line_number');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'invoice_id')
            ->where('invoice_type', 'supplier_invoice');
    }

    // ─── Scopes ─────────────────────────────────────────

    public function scopeOverdue($query)
    {
        return $query->where('payment_status', '!=', 'paid')
            ->where('due_date', '<', now()->toDateString());
    }

    // ─── Accessors ────────────────────────────────────────

    public function getIsOverdueAttribute(): bool
    {
        return $this->payment_status !== 'paid'
            && $this->due_date
            && $this->due_date->isPast();
    }

    // ─── Helpers ─────────────────────────────────────────

    public function recalculateBalance(): void
    {
        $paid = $this->paymentAllocations()->sum('allocated_amount');
        $this->amount_paid        = $paid;
        $this->amount_outstanding = max(0, $this->total_ttc - $paid);
        $this->payment_status = match (true) {
            $this->amount_outstanding <= 0 => 'paid',
            $paid > 0                      => 'partial',
            default                        => 'unpaid',
        };
        $this->save();
    }
}
