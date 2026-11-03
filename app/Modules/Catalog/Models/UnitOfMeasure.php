<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property string $code
 * @property array $name
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class UnitOfMeasure extends Model
{
    use HasTranslations, HasUlids;

    protected $table = 'units_of_measure';

    public array $translatable = ['name'];

    protected $fillable = [
        'code',
        'name',
        'type',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(CatalogItem::class, 'uom_id');
    }
}
