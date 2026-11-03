<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Inventory\Models\StockItem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $catalog_item_id
 * @property string $sku
 * @property string|null $barcode
 * @property float $price
 * @property float $cost_price
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Modules\Catalog\Models\CatalogItem|null $catalogItem
 * @property-read float $current_stock
 */
class CatalogVariant extends Model
{
    use HasUlids;

    protected $table = 'catalog_variants';

    protected $fillable = [
        'catalog_item_id',
        'is_default',
        'name',
        'sku',
        'barcode',
        'price',
        'cost_price',
        'markup',
        'tax_class',
        'min_stock_level',
        'reorder_point',
        'reorder_qty',
        'lead_time_days',
        'status',
    ];

    protected $attributes = [
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'price' => 'decimal:3',
            'cost_price' => 'decimal:3',
            'markup' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CatalogVariant $variant) {
            // If there is no other default variant, force this variant to be default
            $hasOtherDefault = static::query()
                ->where('catalog_item_id', $variant->catalog_item_id)
                ->where('id', '!=', $variant->id ?? '')
                ->where('is_default', true)
                ->exists();

            if (! $hasOtherDefault && ! $variant->is_default) {
                $hasAnyOther = static::query()
                    ->where('catalog_item_id', $variant->catalog_item_id)
                    ->where('id', '!=', $variant->id ?? '')
                    ->exists();

                if (! $hasAnyOther) {
                    $variant->is_default = true;
                }
            }
        });

        static::saved(function (CatalogVariant $variant) {
            if ($variant->is_default) {
                static::query()
                    ->where('catalog_item_id', $variant->catalog_item_id)
                    ->where('id', '!=', $variant->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        static::deleting(function (CatalogVariant $variant) {
            $otherVariantsCount = static::query()
                ->where('catalog_item_id', $variant->catalog_item_id)
                ->where('id', '!=', $variant->id)
                ->count();

            if ($otherVariantsCount === 0) {
                throw new \InvalidArgumentException('Cannot delete the only variant of a catalog item. Every item must have at least one variant.');
            }
        });

        static::deleted(function (CatalogVariant $variant) {
            if ($variant->is_default) {
                $replacement = static::query()
                    ->where('catalog_item_id', $variant->catalog_item_id)
                    ->orderBy('created_at', 'asc')
                    ->first();

                if ($replacement) {
                    $replacement->update(['is_default' => true]);
                }
            }
        });
    }

    // ─── Relationships ───────────────────────────────────────────

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            CatalogOptionValue::class,
            'catalog_variant_option_values',
            'variant_id',
            'option_value_id'
        );
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class, 'catalog_variant_id');
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getEffectiveCostPriceAttribute()
    {
        return $this->cost_price; // Assuming it doesn't fall back to item anymore
    }

    public function getCurrentStockAttribute(): float
    {
        if ($this->relationLoaded('stockItems')) {
            return (float) $this->stockItems->sum('qty_available');
        }

        return (float) $this->stockItems()->sum('qty_available');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope a query to only include default variants.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to only include variants that track stock.
     */
    public function scopeTracksStock($query)
    {
        return $query->whereHas('catalogItem', function ($q) {
            $q->whereIn('item_type', ['product', 'consumable']);
        });
    }
}
