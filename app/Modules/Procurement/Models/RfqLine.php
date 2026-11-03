<?php

namespace App\Modules\Procurement\Models;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $rfq_id
 * @property string|null $catalog_item_id
 * @property string|null $variant_id
 * @property bool $is_ad_hoc
 * @property string|null $ad_hoc_name
 * @property string|null $ad_hoc_sku
 * @property string|null $description_for_supplier
 * @property float $qty_requested
 * @property string $unit_id
 * @property float|null $target_price_ht
 * @property string|null $required_certifications
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read RfqHeader $rfq
 * @property-read CatalogItem|null $catalogItem
 * @property-read \App\Modules\Catalog\Models\CatalogVariant|null $variant
 * @property-read UnitOfMeasure $unit
 */
class RfqLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'rfq_id',
        'catalog_item_id',
        'variant_id',
        'is_ad_hoc',
        'ad_hoc_name',
        'ad_hoc_sku',
        'description_for_supplier',
        'qty_requested',
        'unit_id',
        'target_price_ht',
        'required_certifications',
    ];

    protected function casts(): array
    {
        return [
            'is_ad_hoc' => 'boolean',
            'qty_requested' => 'decimal:3',
            'target_price_ht' => 'decimal:3',
            'required_certifications' => 'array',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(RfqHeader::class, 'rfq_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Catalog\Models\CatalogVariant::class, 'variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }
}
