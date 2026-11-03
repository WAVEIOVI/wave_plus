<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property string $option_id
 * @property string|null $code
 * @property array $value
 * @property float|null $numeric_value
 * @property string|null $unit
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Modules\Catalog\Models\BlueprintOption|null $option
 */
class BlueprintOptionValue extends Model
{
    use HasTranslations, HasUlids;

    protected $table = 'blueprint_option_values';

    public array $translatable = ['value'];

    protected $fillable = [
        'option_id',
        'code',
        'value',
        'numeric_value',
        'unit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'numeric_value' => 'decimal:4',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function option(): BelongsTo
    {
        return $this->belongsTo(BlueprintOption::class, 'option_id');
    }
}
