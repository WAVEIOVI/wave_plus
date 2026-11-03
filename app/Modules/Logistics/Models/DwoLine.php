<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Crm\Models\SalesOrderLine;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Logistics\Enums\DwoLineStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DwoLine extends Model
{
    use HasFactory, SoftDeletes;
    use HasUlids;

    protected $table = 'dwo_lines';

    protected $fillable = [
        'dwo_id',
        'so_item_id',
        'catalog_item_id',
        'catalog_variant_id',
        'commercial_catalog_item_id',
        'commercial_catalog_variant_id',
        'proposed_catalog_item_id',
        'proposed_catalog_variant_id',
        'substitution_status',
        'substitution_reason',
        'substitution_approved_by',
        'substitution_approved_at',
        'description_snapshot',
        'variant_label_snapshot',
        'uom_code_snapshot',
        'qty_ordered',
        'qty_allocated',
        'qty_reserved',
        'qty_shortage',
        'qty_picked',
        'qty_loaded',
        'qty_delivered',
        'qty_returned',
        'lot_number',
        'serial_number',
        'warehouse_location',
        'warehouse_location_id',
        'item_status',
        'condition_on_delivery',
        'driver_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty_ordered' => 'decimal:3',
        'qty_allocated' => 'decimal:3',
        'qty_reserved' => 'decimal:3',
        'qty_shortage' => 'decimal:3',
        'qty_picked' => 'decimal:3',
        'qty_loaded' => 'decimal:3',
        'qty_delivered' => 'decimal:3',
        'qty_returned' => 'decimal:3',
        'substitution_approved_at' => 'datetime',
        'item_status' => DwoLineStatus::class,
    ];

    public function deliveryWorkOrder()
    {
        return $this->belongsTo(DeliveryWorkOrder::class, 'dwo_id');
    }

    public function salesOrderLine()
    {
        return $this->belongsTo(SalesOrderLine::class, 'so_item_id');
    }

    public function catalogItem()
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function catalogVariant()
    {
        return $this->belongsTo(CatalogVariant::class, 'catalog_variant_id');
    }

    public function warehouseLocation()
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }

    public function exceptions()
    {
        return $this->hasMany(DeliveryException::class, 'dwo_line_id');
    }

    public function getQtyRemainingAttribute(): float
    {
        return (float) max(0, ((float) $this->qty_allocated) - ((float) ($this->qty_delivered ?? 0)));
    }
}
