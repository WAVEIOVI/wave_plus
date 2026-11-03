<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Inventory\Models\StockItem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property string $code
 * @property string|null $sku
 * @property string|null $barcode
 * @property string $item_type
 * @property array $name
 * @property array|null $short_description
 * @property int|null $category_id
 * @property int|null $brand_id
 * @property int|null $uom_id
 * @property float|null $base_price
 * @property float|null $cost_price
 * @property float|null $markup
 * @property string|null $tax_class
 * @property bool $is_stockable
 * @property bool $is_purchasable
 * @property bool $is_sellable
 * @property float|null $min_stock_level
 * @property float|null $reorder_point
 * @property float|null $reorder_qty
 * @property int|null $lead_time_days
 * @property int|null $preferred_supplier_id
 * @property int|null $warranty_months
 * @property string|null $internal_notes
 * @property string $status
 * @property array|null $tags
 * @property array|null $certifications
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read float $current_stock
 */
class CatalogItem extends Model implements HasMedia
{
    use HasTranslations, HasUlids, InteractsWithMedia, SoftDeletes;

    protected $table = 'catalog_items';

    public array $translatable = ['name', 'short_description'];

    protected $fillable = [
        'code',
        'barcode',
        'item_type',
        'name',
        'short_description',
        'category_id',
        'brand_id',
        'uom_id',
        'is_stockable',
        'is_purchasable',
        'is_sellable',
        'preferred_supplier_id',
        'warranty_months',
        'internal_notes',
        'status',
        'tags',
        'certifications',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_stockable' => 'boolean',
            'is_purchasable' => 'boolean',
            'is_sellable' => 'boolean',
            'tags' => 'array',
            'certifications' => 'array',
        ];
    }

    // ─── Lifecycle Hooks ────────────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (CatalogItem $item) {
            if ($item->item_type === 'eq_servicing') {
                $item->is_stockable = false;
                $item->is_purchasable = false;
                $item->is_sellable = false;
            }
        });
    }

    // ─── Spatie Media Collections ───────────────────────────────────

    public function registerMediaCollections(): void
    {
        // Images should be publicly accessible
        $this->addMediaCollection('images')
            ->useDisk('public');

        // Documents can be on public disk too
        $this->addMediaCollection('documents')
            ->useDisk('public');

        // Certificates can be on public disk
        $this->addMediaCollection('certificates')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->performOnCollections('images')
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(1200)
            ->height(1200)
            ->performOnCollections('images')
            ->nonQueued();
    }

    // ─── Relationships ───────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(CatalogBrand::class, 'brand_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Extension tables (1-to-1) ────────────────────────────────

    public function product(): HasOne
    {
        return $this->hasOne(CatalogProduct::class, 'catalog_item_id');
    }

    public function consumable(): HasOne
    {
        return $this->hasOne(CatalogConsumable::class, 'catalog_item_id');
    }

    public function eqServicing(): HasOne
    {
        return $this->hasOne(CatalogEqServicing::class, 'catalog_item_id');
    }

    /**
     * Resolve the active extension based on item_type.
     */
    public function extension(): HasOne
    {
        return match ($this->item_type) {
            'consumable' => $this->consumable(),
            'eq_servicing' => $this->eqServicing(),
            default => $this->product(),
        };
    }

    // ── Variant system ───────────────────────────────────────────

    public function options(): HasMany
    {
        return $this->hasMany(CatalogOption::class, 'catalog_item_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(CatalogVariant::class, 'catalog_item_id');
    }

    // ── Inventory (Module C1) ────────────────────────────────────

    /**
     * All stock item records across all warehouse locations for this catalog item.
     * This is the new source of truth for inventory quantities.
     */
    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class, 'catalog_item_id');
    }

    // ── Suppliers (many-to-many) ─────────────────────────────────

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'catalog_item_suppliers', 'catalog_item_id', 'supplier_id')
            ->withPivot(['supplier_reference', 'supplier_price', 'currency', 'lead_time_days', 'min_order_qty', 'is_preferred'])
            ->withTimestamps();
    }

    // ── Tags & Certifications (Json Columns) ─────────────────────

    // Pivot methods removed as tags & certificates are now JSON arrays on the items table.

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to only active items.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter by item type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('item_type', $type);
    }

    /**
     * Scope to search items by code or name.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('short_description', 'like', "%{$term}%");
        });
    }

    // ─── Accessors ────────────────────────────────────────────────

    /**
     * Computed accessor: current available stock across all warehouse locations.
     *
     * Uses the pre-loaded stockItems relation when available (N+1 safe),
     * otherwise falls back to a direct aggregate query.
     * This replaces the deprecated `current_stock` column.
     */
    public function getCurrentStockAttribute(): float
    {
        if ($this->relationLoaded('variants')) {
            return (float) $this->variants->sum(function ($variant) {
                return $variant->current_stock;
            });
        }

        return (float) $this->stockItems()->sum('qty_available');
    }

    /**
     * Total stock on-hand across all locations (aggregated from stock_items).
     *
     * NOTE: The legacy 'stock' column on catalog_variants is NOT the source
     * of truth for inventory. All real inventory is in the stock_items table.
     */
    public function getTotalStockAttribute(): float
    {
        if ($this->relationLoaded('stockItems')) {
            return (float) $this->stockItems->sum('qty_on_hand');
        }

        return (float) $this->stockItems()->sum('qty_on_hand');
    }
}
