<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot: records which option value was selected for a specific workwear item.
 *
 * @property string $id
 * @property string $item_id
 * @property string $option_id
 * @property string $value_id
 */
class WorkwearItemOptionValue extends Model
{
    use HasUlids;

    protected $table = 'workwear_item_option_values';

    protected $fillable = [
        'item_id',
        'option_id',
        'value_id',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function item(): BelongsTo
    {
        return $this->belongsTo(WorkwearItem::class, 'item_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(WorkwearModelOption::class, 'option_id');
    }

    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(WorkwearModelOptionValue::class, 'value_id');
    }
}
