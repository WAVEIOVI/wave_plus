<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    use HasUlids;

    protected $fillable = [
        'payment_id',
        'invoice_type',
        'invoice_id',
        'allocated_amount',
        'allocated_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'allocated_date'   => 'date',
            'allocated_amount' => 'decimal:3',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the related invoice (sales or supplier).
     */
    public function invoice(): BelongsTo
    {
        return match ($this->invoice_type) {
            'sales_invoice'    => $this->belongsTo(SalesInvoice::class, 'invoice_id'),
            'supplier_invoice' => $this->belongsTo(SupplierInvoice::class, 'invoice_id'),
            default            => $this->belongsTo(SalesInvoice::class, 'invoice_id'),
        };
    }
}
