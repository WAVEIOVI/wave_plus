<?php

namespace App\Modules\Procurement\Models;

use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Catalog\Models\CatalogItem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $ipr_id
 * @property string|null $catalog_item_id
 * @property string|null $variant_id
 * @property bool $is_ad_hoc
 * @property string|null $ad_hoc_name
 * @property string|null $ad_hoc_sku
 * @property float $qty_requested
 * @property float|null $qty_current_stock
 * @property float|null $qty_on_order
 * @property int|null $suggested_supplier_id
 * @property float|null $estimated_unit_cost
 * @property float|null $estimated_total_ht
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read InternalPurchaseRequest $ipr
 * @property-read CatalogItem|null $catalogItem
 * @property-read \App\Modules\Catalog\Models\CatalogVariant|null $variant
 * @property-read Supplier|null $suggestedSupplier
 */
class IprLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'ipr_id',
        'catalog_item_id',
        'variant_id',
        'is_ad_hoc',
        'ad_hoc_name',
        'ad_hoc_sku',
        'qty_requested',
        'qty_current_stock',
        'qty_on_order',
        'suggested_supplier_id',
        'estimated_unit_cost',
        'estimated_total_ht',
    ];

    protected $casts = [
        'is_ad_hoc' => 'boolean',
        'qty_requested' => 'decimal:3',
        'qty_current_stock' => 'decimal:3',
        'qty_on_order' => 'decimal:3',
        'estimated_unit_cost' => 'decimal:3',
        'estimated_total_ht' => 'decimal:3',
    ];

    public function ipr(): BelongsTo
    {
        return $this->belongsTo(InternalPurchaseRequest::class, 'ipr_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Catalog\Models\CatalogVariant::class, 'variant_id');
    }

    public function suggestedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'suggested_supplier_id');
    }
}
