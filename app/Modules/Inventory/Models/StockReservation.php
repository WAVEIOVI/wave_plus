<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Catalog\Models\CatalogItem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $stock_item_id
 * @property string $catalog_item_id
 * @property string $reservable_type
 * @property string $reservable_id
 * @property float $qty_reserved
 * @property string $status
 * @property \Illuminate\Support\Carbon $reserved_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $fulfilled_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $reason
 * @property int|null $reserved_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read StockItem $stockItem
 * @property-read CatalogItem $catalogItem
 * @property-read Model|\Illuminate\Database\Eloquent\Model $reservable
 * @property-read User|null $reserver
 */
class StockReservation extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'stock_reservations';

    protected $fillable = [
        'stock_item_id',
        'catalog_item_id',
        'catalog_variant_id',
        'reservable_type',
        'reservable_id',
        'dwo_line_id',
        'sales_order_line_id',
        'qty_reserved',
        'status',
        'reserved_at',
        'expires_at',
        'fulfilled_at',
        'cancelled_at',
        'reason',
        'reserved_by',
    ];

    protected function casts(): array
    {
        return [
            'qty_reserved' => 'decimal:4',
            'reserved_at' => 'datetime',
            'expires_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    /**
     * The document that requested this reservation (Sales Order, etc.).
     */
    public function reservable(): MorphTo
    {
        return $this->morphTo('reservable', 'reservable_type', 'reservable_id');
    }

    public function reserver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    // ─── State Transitions ───────────────────────────────────────

    /**
     * Mark this reservation as fulfilled (stock has been dispatched).
     */
    public function markFulfilled(): self
    {
        $this->status = 'fulfilled';
        $this->fulfilled_at = now();
        $this->save();

        return $this;
    }

    /**
     * Cancel this reservation, releasing the held quantity.
     */
    public function markCancelled(?string $reason = null): self
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($reason) {
            if ($this->status !== 'active') {
                return;
            }

            $this->status = 'cancelled';
            $this->cancelled_at = now();
            $this->reason = $reason ?? $this->reason;
            $this->save();

            $stockItem = StockItem::query()->lockForUpdate()->find($this->stock_item_id);
            if ($stockItem) {
                $stockItem->qty_reserved = (float) bcsub((string) $stockItem->qty_reserved, (string) $this->qty_reserved, 4);
                if (bccomp((string) $stockItem->qty_reserved, '0', 4) < 0) {
                    $stockItem->qty_reserved = 0;
                }
                $stockItem->recalculateAvailable()->save();
            }
        });

        return $this;
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to only active reservations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to expired reservations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }
}
