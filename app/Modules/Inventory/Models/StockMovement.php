<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * StockMovement — Immutable append-only ledger entry.
 *
 * This model is deliberately NOT soft-deletable.
 * Once a movement is recorded, it must never be modified or removed.
 * To "reverse" a movement, create a new compensating movement.
 *
 * @property string $id
 * @property string $reference
 * @property string $movement_type
 * @property string|null $source_document_type
 * @property string|null $source_document_id
 * @property string|null $source_line_id
 * @property string $catalog_item_id
 * @property string|null $stock_lot_id
 * @property float $quantity
 * @property int|null $uom_id
 * @property string|null $from_location_id
 * @property string|null $to_location_id
 * @property float|null $unit_cost
 * @property float|null $total_cost
 * @property string|null $currency
 * @property string|null $reason
 * @property array|null $metadata
 * @property int|null $performed_by
 * @property \Illuminate\Support\Carbon $performed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read CatalogItem $catalogItem
 * @property-read StockLot|null $stockLot
 * @property-read UnitOfMeasure|null $uom
 * @property-read WarehouseLocation|null $fromLocation
 * @property-read WarehouseLocation|null $toLocation
 * @property-read User|null $performer
 * @property-read Model|\Illuminate\Database\Eloquent\Model|null $sourceDocument
 */
class StockMovement extends Model
{
    use HasUlids;

    // ── No SoftDeletes — by design. ──

    protected $table = 'stock_movements';

    protected $fillable = [
        'reference',
        'movement_type',
        'source_document_type',
        'source_document_id',
        'source_line_id',
        'catalog_item_id',
        'catalog_variant_id',
        'stock_lot_id',
        'quantity',
        'uom_id',
        'from_location_id',
        'to_location_id',
        'unit_cost',
        'total_cost',
        'currency',
        'reason',
        'metadata',
        'performed_by',
        'performed_at',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            abort(422, 'Stock movements are immutable. Create a compensating movement instead.');
        });

        static::deleting(function () {
            abort(422, 'Stock movements cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:3',
            'total_cost' => 'decimal:3',
            'metadata' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function catalogVariant(): BelongsTo
    {
        return $this->belongsTo(CatalogVariant::class, 'catalog_variant_id');
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'to_location_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // ─── Polymorphic Source Document ─────────────────────────────

    /**
     * Resolve the source document (GRN, SO, Transfer, etc.).
     */
    public function sourceDocument(): MorphTo
    {
        return $this->morphTo('source_document', 'source_document_type', 'source_document_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to filter by movement type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('movement_type', $type);
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

    /**
     * Scope to filter by date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $from
     * @param  mixed  $to
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInDateRange($query, $from, $to)
    {
        return $query->whereBetween('performed_at', [$from, $to]);
    }
}
