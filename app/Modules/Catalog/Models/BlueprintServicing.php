<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Level-1 Servicing: Links a Blueprint to an eq_servicing CatalogItem.
 *
 * @property string $id
 * @property string $blueprint_id
 * @property string $servicing_item_id
 * @property array|null $applicability_conditions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Modules\Catalog\Models\EquipmentBlueprint|null $blueprint
 * @property-read \App\Modules\Catalog\Models\CatalogItem|null $servicingItem
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Modules\Catalog\Models\BlueprintServicingConsumable[] $consumables
 */
class BlueprintServicing extends Model
{
    use HasUlids;

    protected $table = 'blueprint_servicing';

    protected $fillable = [
        'blueprint_id',
        'servicing_item_id',
        'applicability_conditions',
    ];

    protected function casts(): array
    {
        return [
            'applicability_conditions' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(EquipmentBlueprint::class, 'blueprint_id');
    }

    /**
     * The eq_servicing catalog item (e.g. "Refill / Recharge").
     */
    public function servicingItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'servicing_item_id');
    }

    /**
     * Level-2: Consumables required by this servicing.
     */
    public function consumables(): HasMany
    {
        return $this->hasMany(BlueprintServicingConsumable::class, 'blueprint_servicing_id');
    }
}
