<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $catalog_item_id
 * @property string $eq_servicing_type
 * @property string|null $delivery_mode
 * @property string|null $billable_unit
 * @property int|null $duration_minutes
 * @property bool $is_recurring
 * @property array|null $required_certifications
 * @property array|null $technical_specs
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CatalogEqServicing extends Model
{
    use HasUlids;

    protected $table = 'catalog_eq_servicing';

    protected $primaryKey = 'catalog_item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'catalog_item_id',
        'eq_servicing_type',
        'delivery_mode',
        'billable_unit',
        'duration_minutes',
        'is_recurring',
        'required_certifications',
        'technical_specs',
    ];

    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'required_certifications' => 'array',
            'technical_specs' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    /**
     * Consumables / parts list for this servicing type.
     */
    public function consumables(): BelongsToMany
    {
        return $this->belongsToMany(
            CatalogItem::class,
            'servicing_consumables',
            'servicing_id',
            'catalog_item_id',
        )->using(ServicingConsumable::class)->withPivot(['quantity', 'unit'])->withTimestamps();
    }
}
