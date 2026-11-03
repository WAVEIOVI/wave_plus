<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $catalog_item_id
 * @property string $consumable_type
 * @property bool $is_consumable
 * @property int|null $shelf_life_months
 * @property string|null $storage_conditions
 * @property array|null $technical_specs
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CatalogConsumable extends Model
{
    use HasUlids;

    protected $table = 'catalog_consumables';

    protected $primaryKey = 'catalog_item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'catalog_item_id',
        'consumable_type',
        'is_consumable',
        'shelf_life_months',
        'storage_conditions',
        'technical_specs',
    ];

    protected function casts(): array
    {
        return [
            'is_consumable' => 'boolean',
            'technical_specs' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }
}
