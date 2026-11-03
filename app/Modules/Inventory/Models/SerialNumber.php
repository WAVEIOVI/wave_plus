<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Catalog\Models\CatalogItem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $catalog_item_id
 * @property string|null $stock_lot_id
 * @property string $serial
 * @property string $status
 * @property string|null $current_location_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read CatalogItem $catalogItem
 * @property-read StockLot|null $stockLot
 * @property-read WarehouseLocation|null $currentLocation
 * @property-read User|null $creator
 */
class SerialNumber extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'serial_numbers';

    protected $fillable = [
        'catalog_item_id',
        'stock_lot_id',
        'serial',
        'status',
        'current_location_id',
        'notes',
        'created_by',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'current_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to only available serial numbers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope to filter by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
