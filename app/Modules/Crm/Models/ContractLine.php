<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $contract_id
 * @property string|null $catalog_item_id
 * @property string|null $variant_id
 * @property bool $is_ad_hoc
 * @property string|null $ad_hoc_name
 * @property string|null $ad_hoc_sku
 * @property float $contracted_qty
 * @property float $contracted_unit_price
 * @property float $called_off_qty
 * @property float $remaining_qty
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Contract $contract
 * @property-read CatalogItem|null $catalogItem
 * @property-read CatalogVariant|null $variant
 */
class ContractLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'contract_id',
        'catalog_item_id',
        'variant_id',
        'is_ad_hoc',
        'ad_hoc_name',
        'ad_hoc_sku',
        'contracted_qty',
        'contracted_unit_price',
        'called_off_qty',
        'remaining_qty',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_ad_hoc' => 'boolean',
            'contracted_qty' => 'decimal:3',
            'contracted_unit_price' => 'decimal:3',
            'called_off_qty' => 'decimal:3',
            'remaining_qty' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(CatalogVariant::class, 'variant_id');
    }
}
