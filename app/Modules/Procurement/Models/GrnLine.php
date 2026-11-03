<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Inventory\Models\WarehouseLocation;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $grn_id
 * @property string $po_line_id
 * @property string $catalog_item_id
 * @property float $qty_received
 * @property float $qty_accepted
 * @property float $qty_rejected
 * @property float $unit_cost_ht
 * @property string|null $lot_number
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property string|null $storage_location_id
 * @property string|null $qc_result
 * @property string|null $qc_notes
 * @property array|null $qc_photos
 * @property array|null $certificates_received
 * @property bool $is_ad_hoc
 * @property string|null $ad_hoc_name
 * @property string|null $ad_hoc_sku
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read GoodsReceiptNote $grn
 * @property-read PoLine $poLine
 * @property-read CatalogItem $catalogItem
 * @property-read CatalogVariant $catalogVariant
 * @property-read WarehouseLocation|null $storageLocation
 */
class GrnLine extends Model
{
    use HasUlids;

    protected $table = 'grn_lines';

    protected $fillable = [
        'grn_id',
        'po_line_id',
        'catalog_item_id',
        'catalog_variant_id',
        'qty_received',
        'qty_accepted',
        'qty_rejected',
        'unit_cost_ht',
        'lot_number',
        'expiry_date',
        'storage_location_id',
        'qc_result',
        'qc_notes',
        'qc_photos',
        'certificates_received',
        'is_ad_hoc',
        'ad_hoc_name',
        'ad_hoc_sku',
    ];

    protected function casts(): array
    {
        return [
            'qty_received' => 'decimal:3',
            'qty_accepted' => 'decimal:3',
            'qty_rejected' => 'decimal:3',
            'unit_cost_ht' => 'decimal:3',
            'expiry_date' => 'date',
            'qc_photos' => 'array',
            'certificates_received' => 'array',
            'is_ad_hoc' => 'boolean',
        ];
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class, 'grn_id');
    }

    public function poLine(): BelongsTo
    {
        return $this->belongsTo(PoLine::class, 'po_line_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function catalogVariant(): BelongsTo
    {
        return $this->belongsTo(CatalogVariant::class, 'catalog_variant_id');
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'storage_location_id');
    }
}
