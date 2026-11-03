<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'supplier_invoice_id',
        'line_number',
        'description',
        'catalog_item_id',
        'quantity',
        'unit_price_ht',
        'tax_rate_id',
        'tax_rate_pct',
        'line_ht',
        'line_tax',
        'line_ttc',
        'cost_center_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'decimal:4',
            'unit_price_ht' => 'decimal:3',
            'tax_rate_pct'  => 'decimal:4',
            'line_ht'       => 'decimal:3',
            'line_tax'      => 'decimal:3',
            'line_ttc'      => 'decimal:3',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }
}
