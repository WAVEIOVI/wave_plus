<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property array $name
 * @property int|null $parent_id
 * @property string $item_type_scope
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CatalogCategory extends Model
{
    use HasTranslations, HasUlids;

    protected $table = 'catalog_categories';

    public array $translatable = ['name'];

    protected $fillable = [
        'name',
        'parent_id',
        'item_type_scope',
        'item_subtype_scope',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'item_type_scope' => 'array',
            'item_subtype_scope' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CatalogItem::class, 'category_id');
    }
}
