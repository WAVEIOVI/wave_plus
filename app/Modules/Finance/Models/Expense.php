<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'expense_number',
        'expense_date',
        'financial_period_id',
        'category',
        'currency',
        'amount_ht',
        'tax_amount',
        'amount_ttc',
        'tax_rate_id',
        'financial_account_id',
        'cost_center_id',
        'provider_type',
        'provider_id',
        'provider_name',
        'vehicle_id',
        'sales_order_id',
        'mission_reference',
        'dwo_reference',
        'related_doc_type',
        'related_doc_id',
        'status',
        'posted_at',
        'posted_by',
        'description',
        'notes',
        'receipt_path',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'posted_at'    => 'datetime',
            'amount_ht'    => 'decimal:3',
            'tax_amount'   => 'decimal:3',
            'amount_ttc'   => 'decimal:3',
        ];
    }

    // ─── Relationships ──────────────────────────────────

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ─────────────────────────────────────────

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function scopeForVehicle($query, string $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeForCostCenter($query, string $costCenterId)
    {
        return $query->where('cost_center_id', $costCenterId);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
