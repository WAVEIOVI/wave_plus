<?php

namespace App\Modules\Catalog\Models;

use App\Modules\BusinessPartners\Models\Supplier;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogItemSupplier extends Model
{
    use HasUlids;

    protected $table = 'catalog_item_suppliers';

    public $incrementing = false;

    protected $fillable = [
        'catalog_item_id',
        'supplier_id',
        'supplier_reference',
        'supplier_price',
        'currency',
        'lead_time_days',
        'min_order_qty',
        'is_preferred',
    ];

    protected function casts(): array
    {
        return [
            'supplier_price' => 'decimal:3',
            'min_order_qty' => 'decimal:2',
            'is_preferred' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
