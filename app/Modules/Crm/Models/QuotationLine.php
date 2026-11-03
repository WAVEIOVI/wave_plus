<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Crm\Enums\ItemTypeSnapshot;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $quotation_id
 * @property string|null $catalog_item_id
 * @property string|null $variant_id
 * @property bool $is_ad_hoc
 * @property string|null $ad_hoc_name
 * @property string|null $ad_hoc_sku
 * @property string|null $description_override
 * @property float $qty
 * @property float $unit_price
 * @property float $discount_pct
 * @property float $unit_price_after_discount
 * @property string|null $tax_class_id
 * @property float $tva_rate_pct
 * @property float $total_ht
 * @property float $total_tva
 * @property float $total_ttc
 * @property int $sort_order
 * @property string|null $note
 * @property ItemTypeSnapshot|null $item_type_snapshot
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Quotation $quotation
 * @property-read CatalogItem|null $catalogItem
 * @property-read CatalogVariant|null $variant
 */
class QuotationLine extends Model
{
    use HasUlids;

    protected $fillable = [
        'quotation_id',
        'catalog_item_id',
        'variant_id',
        'is_ad_hoc',
        'ad_hoc_name',
        'ad_hoc_sku',
        'description_override',
        'qty',
        'internal_cost',
        'actual_margin_pct',
        'unit_price',
        'discount_pct',
        'unit_price_after_discount',
        'tax_class_id',
        'tva_rate_pct',
        'total_ht',
        'total_tva',
        'total_ttc',
        'sort_order',
        'note',
        'item_type_snapshot',
        'description_snapshot',
        'sku_snapshot',
        'uom_code_snapshot',
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
            'internal_cost' => 'decimal:3',
            'actual_margin_pct' => 'decimal:2',
            'unit_price' => 'decimal:3',
            'discount_pct' => 'decimal:2',
            'unit_price_after_discount' => 'decimal:3',
            'tva_rate_pct' => 'decimal:2',
            'total_ht' => 'decimal:3',
            'total_tva' => 'decimal:3',
            'total_ttc' => 'decimal:3',
            'sort_order' => 'integer',
            'source_blueprint_option_values' => 'array',
            'calculation_snapshot' => 'array',
        ];
    }

    protected static function booted()
    {
        static::updated(function (self $line) {
            // Bi-directional live sync: update linked PR line if exists
            $quotation = $line->quotation;
            $status = $quotation?->status?->value ?? (string) $quotation?->status;
            if ($quotation && $quotation->pr_id && in_array($status, ['draft', 'rejected'], true)) {
                // Find matching PR line based on catalog_item, variant, and ad_hoc_name
                $prQuery = PriceRequestLine::where('price_request_id', $quotation->pr_id)
                    ->where('is_ad_hoc', $line->is_ad_hoc);

                if ($line->is_ad_hoc) {
                    $prQuery->where('ad_hoc_name', $line->ad_hoc_name);
                } else {
                    $prQuery->where('catalog_item_id', $line->catalog_item_id)
                        ->where('variant_id', $line->variant_id);
                }

                $prLine = $prQuery->first();
                if ($prLine) {
                    $prLine->update([
                        'proposed_price' => $line->unit_price,
                        'discount_pct' => $line->discount_pct,
                        'actual_margin_pct' => $line->actual_margin_pct,
                    ]);
                }
            }
        });
    }

    // ─── Relationships ───────────────────────────────────────────

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
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
