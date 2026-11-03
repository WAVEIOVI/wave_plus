<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialAccount extends Model
{
    use HasUlids, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (FinancialAccount $account) {
            if (empty($account->code)) {
                $prefix = match ($account->type ?? 'bank') {
                    'bank' => 'BNK',
                    'cash' => 'CSH',
                    'petty_cash' => 'CSH',
                    'digital_wallet' => 'WAL',
                    default => 'ACC',
                };
                $account->code = $prefix . '-' . strtoupper(substr(uniqid(), -6));
            }
            if (empty($account->currency)) {
                $account->currency = 'TND';
            }
        });
    }

    protected $fillable = [
        'code',
        'name',
        'type',
        'currency',
        'opening_balance',
        'current_balance',
        'bank_name',
        'bank_account_number',
        'iban',
        'swift_bic',
        'status',
        'reconciliation_status',
        'last_reconciled_date',
        'coa_account_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance'    => 'decimal:3',
            'current_balance'    => 'decimal:3',
            'last_reconciled_date' => 'date',
        ];
    }

    // ─── Relationships ──────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialAccountTransaction::class, 'financial_account_id')
            ->orderByDesc('transaction_date');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'financial_account_id');
    }

    // ─── Scopes ─────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ─── Accessors ────────────────────────────────────────

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }
}
