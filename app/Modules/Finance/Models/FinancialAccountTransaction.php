<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAccountTransaction extends Model
{
    use HasUlids;

    protected $fillable = [
        'financial_account_id',
        'transaction_date',
        'type',
        'amount',
        'balance_after',
        'reference',
        'description',
        'source_type',
        'source_id',
        'reconciliation_status',
        'reconciled_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'reconciled_date'  => 'date',
            'amount'           => 'decimal:3',
            'balance_after'    => 'decimal:3',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
