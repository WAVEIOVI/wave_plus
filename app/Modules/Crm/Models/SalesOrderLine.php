<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Crm\Enums\ItemTypeSnapshot;
use App\Modules\Logistics\Models\DwoLine;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $sales_order_id
 * @property string|null $catalog_item_id
 * @property string|null $variant_id
 * @property bool $is_ad_hoc
 * @property string|null $ad_hoc_name
 * @property string|null $ad_hoc_sku
 * @property ItemTypeSnapshot|null $item_type_snapshot
 * @property float $qty
 * @property float $reserved_qty
 * @property float $fulfilled_qty
 * @property string|null $unit_id
 * @property float $unit_price
 * @property float $discount_pct
 * @property float $total_ht
 * @property float $total_tva
 * @property float $total_ttc
 * @property string|null $target_site_id
 * @property array|null $target_equipment_ids
 * @property string $fulfillment_status
 * @property \Illuminate\Support\Carbon|null $delivery_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read SalesOrder $salesOrder
 * @property-read CatalogItem|null $catalogItem
 * @property-read CatalogVariant|null $variant
 * @property-read UnitOfMeasure|null $unit
 * @property-read \Illuminate\Database\Eloquent\Collection|DwoLine[] $dwoItems
 * @property-read \Illuminate\Database\Eloquent\Collection|SwoItem[] $swoItems
 */
class SalesOrderLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'sales_order_id',
        'catalog_item_id',
        'variant_id',
        'is_ad_hoc',
        'ad_hoc_name',
        'ad_hoc_sku',
        'item_type_snapshot',
        'description_snapshot',
        'sku_snapshot',
        'uom_code_snapshot',
        'qty',
        'reserved_qty',
        'fulfilled_qty',
        'unit_id',
        'unit_price',
        'discount_pct',
        'total_ht',
        'total_tva',
        'total_ttc',
        'target_site_id',
        'target_equipment_ids',
        'fulfillment_status',
        'delivery_date',
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
            'item_type_snapshot' => ItemTypeSnapshot::class,
            'qty' => 'decimal:3',
            'reserved_qty' => 'decimal:3',
            'fulfilled_qty' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'discount_pct' => 'decimal:2',
            'total_ht' => 'decimal:3',
            'total_tva' => 'decimal:3',
            'total_ttc' => 'decimal:3',
            'target_equipment_ids' => 'array',
            'delivery_date' => 'date',
            'source_blueprint_option_values' => 'array',
            'calculation_snapshot' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(CatalogVariant::class, 'variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function dwoItems(): HasMany
    {
        return $this->hasMany(DwoLine::class, 'so_item_id');
    }

    public function swoItems(): HasMany
    {
        return $this->hasMany(SwoItem::class, 'so_line_id');
    }

    public function equipmentBlueprint(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Catalog\Models\EquipmentBlueprint::class, 'source_blueprint_id');
    }

    public function sourceServicing(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Catalog\Models\BlueprintServicing::class, 'source_servicing_id');
    }

    public function sourceConsumable(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Catalog\Models\BlueprintServicingConsumable::class, 'source_consumable_id');
    }
}
