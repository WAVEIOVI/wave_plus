<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'payment_number',
        'direction',
        'type',
        'payment_date',
        'financial_period_id',
        'financial_account_id',
        'party_type',
        'party_id',
        'party_name',
        'method',
        'reference',
        'currency',
        'amount',
        'amount_allocated',
        'amount_unallocated',
        'status',
        'confirmed_at',
        'confirmed_by',
        'cost_center_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date'       => 'date',
            'confirmed_at'       => 'datetime',
            'amount'             => 'decimal:3',
            'amount_allocated'   => 'decimal:3',
            'amount_unallocated' => 'decimal:3',
        ];
    }

    // ─── Relationships ──────────────────────────────────

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ─────────────────────────────────────────

    public function scopeIn($query)
    {
        return $query->where('direction', 'in');
    }

    public function scopeOut($query)
    {
        return $query->where('direction', 'out');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeForParty($query, string $partyType, string $partyId)
    {
        return $query->where('party_type', $partyType)->where('party_id', $partyId);
    }

    // ─── Helpers ─────────────────────────────────────────

    public function refreshAllocationTotals(): void
    {
        $allocated = $this->allocations()->sum('allocated_amount');
        $this->amount_allocated   = $allocated;
        $this->amount_unallocated = max(0, $this->amount - $allocated);
        $this->saveQuietly();
    }
}
