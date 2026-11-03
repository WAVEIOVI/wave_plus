<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class CatalogOption extends Model
{
    use HasTranslations, HasUlids;

    protected $table = 'catalog_options';

    public array $translatable = ['name'];

    protected $fillable = [
        'catalog_item_id',
        'name',
        'sort_order',
        'ui_control',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CatalogOptionValue::class, 'option_id')
            ->orderBy('sort_order');
    }
}
