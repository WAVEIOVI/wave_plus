<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Level-2 Servicing Consumable: Links a BlueprintServicing row to a consumable CatalogItem
 * with a calculation engine for quantity determination.
 *
 * Canonical calculation:
 *   type=fixed                          → quantity = fixed_quantity
 *   type=blueprint_option + method+param → quantity = f(numeric_value, method, parameter)
 *
 * @property string $id
 * @property string $blueprint_servicing_id
 * @property string $consumable_item_id
 * @property string $calculation_type        fixed | blueprint_option
 * @property string|null $calculation_source_option_id
 * @property string $calculation_method      fixed | multiply | add | subtract | divide
 * @property float|null $calculation_parameter
 * @property float|null $fixed_quantity
 * @property array|null $applicability_conditions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Modules\Catalog\Models\BlueprintServicing|null $servicing
 * @property-read \App\Modules\Catalog\Models\CatalogItem|null $consumableItem
 * @property-read \App\Modules\Catalog\Models\BlueprintOption|null $calculationSourceOption
 */
class BlueprintServicingConsumable extends Model
{
    use HasUlids;

    protected $table = 'blueprint_servicing_consumables';

    protected $fillable = [
        'blueprint_servicing_id',
        'consumable_item_id',
        'calculation_type',
        'calculation_source_option_id',
        'calculation_method',
        'calculation_parameter',
        'fixed_quantity',
        'applicability_conditions',
    ];

    protected function casts(): array
    {
        return [
            'calculation_parameter' => 'decimal:4',
            'fixed_quantity' => 'decimal:4',
            'applicability_conditions' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    /**
     * The parent servicing row.
     */
    public function servicing(): BelongsTo
    {
        return $this->belongsTo(BlueprintServicing::class, 'blueprint_servicing_id');
    }

    /**
     * The consumable catalog item (e.g. "ABC Powder").
     */
    public function consumableItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'consumable_item_id');
    }

    /**
     * The source blueprint option for calculations (e.g. "Capacity").
     */
    public function calculationSourceOption(): BelongsTo
    {
        return $this->belongsTo(BlueprintOption::class, 'calculation_source_option_id');
    }
}
