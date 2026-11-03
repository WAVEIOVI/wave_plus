<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $price_list_id
 * @property string $catalog_item_id
 * @property string $variant_id
 * @property string $price_computation
 * @property float|null $fixed_price
 * @property float|null $discount_pct
 * @property float|null $min_qty
 * @property float|null $max_qty
 * @property float|null $computed_price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read PriceList $priceList
 * @property-read CatalogItem $catalogItem
 * @property-read CatalogVariant $variant
 */
class PriceListLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'price_list_id',
        'catalog_item_id',
        'variant_id',
        'price_computation',
        'fixed_price',
        'discount_pct',
        'min_qty',
        'max_qty',
        'computed_price',
        'notes',
        'internal_cost',
        'sales_price',
        'source_type',
        'source_blueprint_id',
        'source_blueprint_option_values',
        'source_servicing_id',
        'source_consumable_id',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'fixed_price' => 'decimal:3',
            'discount_pct' => 'decimal:2',
            'min_qty' => 'decimal:3',
            'max_qty' => 'decimal:3',
            'computed_price' => 'decimal:3',
            'internal_cost' => 'decimal:3',
            'sales_price' => 'decimal:3',
            'source_blueprint_option_values' => 'array',
            'calculation_snapshot' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(CatalogVariant::class, 'variant_id');
    }
}
