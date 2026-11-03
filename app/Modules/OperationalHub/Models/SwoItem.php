<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Crm\Models\SalesOrderLine;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwoItem extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'swo_items';

    protected $fillable = [
        'swo_id',
        'so_line_id',
        'catalog_item_id',
        'qty_ordered',
        'qty_completed',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty_ordered' => 'decimal:3',
        'qty_completed' => 'decimal:3',
    ];

    public function serviceWorkOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkOrder::class, 'swo_id');
    }

    public function soLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'so_line_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
