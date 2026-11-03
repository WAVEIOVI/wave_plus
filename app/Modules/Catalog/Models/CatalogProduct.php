<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $catalog_item_id
 * @property string $product_type
 * @property string|null $physical_type
 * @property float|null $capacity_value
 * @property string|null $capacity_unit
 * @property string|null $material
 * @property array|null $technical_specs
 * @property int|null $expiry_years
 * @property string|null $digital_type
 * @property string|null $delivery_method
 * @property bool $is_downloadable
 * @property string|null $service_type
 * @property string|null $billable_unit
 * @property int|null $duration_minutes
 * @property int|null $max_participants
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CatalogProduct extends Model
{
    use HasUlids;

    protected $table = 'catalog_products';

    protected $primaryKey = 'catalog_item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'catalog_item_id',
        'product_type',
        'physical_type',
        'capacity_value',
        'capacity_unit',
        'material',
        'technical_specs',
        'expiry_years',
        'digital_type',
        'delivery_method',
        'is_downloadable',
        'service_type',
        'billable_unit',
        'duration_minutes',
        'max_participants',
    ];

    protected function casts(): array
    {
        return [
            'capacity_value' => 'decimal:3',
            'technical_specs' => 'array',
            'is_downloadable' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }
}
