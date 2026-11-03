<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Catalog\Models\CatalogItem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $catalog_item_id
 * @property int|null $supplier_id
 * @property string|null $lot_number
 * @property \Illuminate\Support\Carbon|null $manufacture_date
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property string $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read CatalogItem $catalogItem
 * @property-read Supplier|null $supplier
 * @property-read User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection|SerialNumber[] $serialNumbers
 * @property-read \Illuminate\Database\Eloquent\Collection|StockItem[] $stockItems
 */
class StockLot extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'stock_lots';

    protected $fillable = [
        'catalog_item_id',
        'supplier_id',
        'lot_number',
        'manufacture_date',
        'expiry_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'manufacture_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'stock_lot_id');
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class, 'stock_lot_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to only active stock lots.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to expired stock lots.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
            ->orWhere(fn ($q) => $q->whereNotNull('expiry_date')->where('expiry_date', '<', now()));
    }
}
