<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A possible value for a workwear model option (e.g. "S", "M", "L", "XL").
 *
 * @property string $id
 * @property string $option_id
 * @property string $value
 * @property int $sort_order
 */
class WorkwearModelOptionValue extends Model
{
    use HasUlids;

    protected $table = 'workwear_model_option_values';

    protected $fillable = [
        'option_id',
        'value',
        'sort_order',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function option(): BelongsTo
    {
        return $this->belongsTo(WorkwearModelOption::class, 'option_id');
    }
}
