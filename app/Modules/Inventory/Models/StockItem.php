<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\CatalogItem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $catalog_item_id
 * @property string $catalog_variant_id
 * @property string $location_id
 * @property string|null $stock_lot_id
 * @property float $qty_on_hand
 * @property float $qty_reserved
 * @property float $qty_available
 * @property float $qty_in_transit
 * @property float|null $unit_cost
 * @property string $currency
 * @property string $condition
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read CatalogItem $catalogItem
 * @property-read \App\Modules\Catalog\Models\CatalogVariant $catalogVariant
 * @property-read WarehouseLocation $location
 * @property-read StockLot|null $stockLot
 * @property-read \Illuminate\Database\Eloquent\Collection|StockReservation[] $reservations
 * @property-read \Illuminate\Database\Eloquent\Collection|StockReservation[] $activeReservations
 */
class StockItem extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'stock_items';

    protected $fillable = [
        'catalog_item_id',
        'catalog_variant_id',
        'location_id',
        'stock_lot_id',
        'qty_on_hand',
        'qty_reserved',
        'qty_available',
        'qty_in_transit',
        'unit_cost',
        'currency',
        'condition',
        'status',
    ];

    /**
     * The generated column used for the unique constraint should never
     * appear in JSON responses or be mass-assigned.
     */
    protected $hidden = ['stock_lot_id_uni'];

    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'decimal:4',
            'qty_reserved' => 'decimal:4',
            'qty_available' => 'decimal:4',
            'qty_in_transit' => 'decimal:4',
            'unit_cost' => 'decimal:3',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function catalogVariant(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Catalog\Models\CatalogVariant::class, 'catalog_variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class, 'stock_item_id');
    }

    public function activeReservations(): HasMany
    {
        return $this->hasMany(StockReservation::class, 'stock_item_id')
            ->where('status', 'active');
    }

    // ─── Quantity Helpers ────────────────────────────────────────

    /**
     * Recalculate qty_available from on_hand minus reserved.
     * Call after any qty_on_hand or qty_reserved change.
     */
    public function recalculateAvailable(): self
    {
        $this->qty_available = (float) bcsub((string) $this->qty_on_hand, (string) $this->qty_reserved, 4);

        return $this;
    }

    /**
     * Increment qty_on_hand and update qty_available accordingly.
     */
    public function incrementOnHand(string $qty): self
    {
        $this->qty_on_hand = (float) bcadd((string) $this->qty_on_hand, $qty, 4);

        return $this->recalculateAvailable();
    }

    /**
     * Decrement qty_on_hand and update qty_available accordingly.
     */
    public function decrementOnHand(string $qty): self
    {
        $this->qty_on_hand = (float) bcsub((string) $this->qty_on_hand, $qty, 4);

        return $this->recalculateAvailable();
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to only active stock items.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to only items with available stock.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithAvailableStock($query)
    {
        return $query->where('qty_available', '>', 0);
    }

    /**
     * Scope to filter by catalog item.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForItem($query, string $catalogItemId)
    {
        return $query->where('catalog_item_id', $catalogItemId);
    }
}
