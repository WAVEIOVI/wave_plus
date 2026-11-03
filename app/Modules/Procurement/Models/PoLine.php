<?php

namespace App\Modules\Procurement\Models;

use App\Modules\Catalog\Models\CatalogItem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $po_id
 * @property string|null $catalog_item_id
 * @property string|null $variant_id
 * @property bool $is_ad_hoc
 * @property string|null $ad_hoc_name
 * @property string|null $ad_hoc_sku
 * @property string|null $ipr_line_id
 * @property string|null $supplier_reference
 * @property string $description_snapshot
 * @property float $qty_ordered
 * @property float $unit_price_ht
 * @property float $tax_rate_pct
 * @property float $total_ht
 * @property float $total_tax
 * @property float $total_ttc
 * @property float $qty_received
 * @property string $fulfillment_status
 * @property \Illuminate\Support\Carbon|null $expected_delivery_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read CatalogItem|null $catalogItem
 * @property-read \App\Modules\Catalog\Models\CatalogVariant|null $variant
 */
class PoLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'po_id',
        'catalog_item_id',
        'variant_id',
        'is_ad_hoc',
        'ad_hoc_name',
        'ad_hoc_sku',
        'ipr_line_id',
        'supplier_reference',
        'description_snapshot',
        'qty_ordered',
        'unit_price_ht',
        'tax_rate_pct',
        'total_ht',
        'total_tax',
        'total_ttc',
        'qty_received',
        'fulfillment_status',
        'expected_delivery_date',
    ];

    protected $casts = [
        'is_ad_hoc' => 'boolean',
        'qty_ordered' => 'decimal:2',
        'unit_price_ht' => 'decimal:3',
        'tax_rate_pct' => 'decimal:2',
        'total_ht' => 'decimal:3',
        'total_tax' => 'decimal:3',
        'total_ttc' => 'decimal:3',
        'qty_received' => 'decimal:2',
        'expected_delivery_date' => 'date',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Catalog\Models\CatalogVariant::class, 'variant_id');
    }
}
