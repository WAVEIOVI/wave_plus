<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterventionMaterial extends Model
{
    use HasUlids;

    protected $table = 'intervention_materials';

    protected $fillable = [
        'intervention_id',
        'catalog_item_id',
        'catalog_variant_id',
        'description_snapshot',
        'qty_used',
        'uom_code_snapshot',
        'variant_sku_snapshot',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'qty_used' => 'decimal:3',
    ];

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class, 'intervention_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function catalogVariant(): BelongsTo
    {
        return $this->belongsTo(CatalogVariant::class, 'catalog_variant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
