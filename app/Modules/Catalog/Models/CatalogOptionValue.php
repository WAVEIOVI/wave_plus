<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class CatalogOptionValue extends Model
{
    use HasTranslations, HasUlids;

    protected $table = 'catalog_option_values';

    public array $translatable = ['value'];

    protected $fillable = [
        'option_id',
        'value',
        'sort_order',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function option(): BelongsTo
    {
        return $this->belongsTo(CatalogOption::class, 'option_id');
    }
}
