<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property string $blueprint_id
 * @property string|null $code
 * @property array $name
 * @property string $data_type
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Modules\Catalog\Models\EquipmentBlueprint|null $blueprint
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Modules\Catalog\Models\BlueprintOptionValue[] $values
 */
class BlueprintOption extends Model
{
    use HasTranslations, HasUlids;

    protected $table = 'blueprint_options';

    public array $translatable = ['name'];

    protected $fillable = [
        'blueprint_id',
        'code',
        'name',
        'data_type',
        'sort_order',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(EquipmentBlueprint::class, 'blueprint_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(BlueprintOptionValue::class, 'option_id')->orderBy('sort_order');
    }
}
