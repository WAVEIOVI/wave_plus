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
 * @property string $price_request_id
 * @property string|null $catalog_item_id
 * @property string|null $variant_id
 * @property bool $is_ad_hoc
 * @property string|null $ad_hoc_name
 * @property string|null $ad_hoc_sku
 * @property float $qty_requested
 * @property float|null $internal_cost
 * @property float|null $target_margin_pct
 * @property float|null $suggested_price
 * @property float|null $proposed_price
 * @property float|null $actual_margin_pct
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read PriceRequest $priceRequest
 * @property-read CatalogItem|null $catalogItem
 * @property-read CatalogVariant|null $variant
 */
class PriceRequestLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'price_request_id',
        'catalog_item_id',
        'variant_id',
        'is_ad_hoc',
        'ad_hoc_name',
        'ad_hoc_sku',
        'qty_requested',
        'internal_cost',
        'target_margin_pct',
        'suggested_price',
        'proposed_price',
        'discount_pct',
        'actual_margin_pct',
        'tva_rate_pct',
        'notes',
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
            'is_ad_hoc' => 'boolean',
            'qty_requested' => 'decimal:3',
            'internal_cost' => 'decimal:3',
            'target_margin_pct' => 'decimal:2',
            'suggested_price' => 'decimal:3',
            'proposed_price' => 'decimal:3',
            'discount_pct' => 'decimal:2',
            'actual_margin_pct' => 'decimal:2',
            'tva_rate_pct' => 'decimal:2',
            'source_blueprint_option_values' => 'array',
            'calculation_snapshot' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function priceRequest(): BelongsTo
    {
        return $this->belongsTo(PriceRequest::class);
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
