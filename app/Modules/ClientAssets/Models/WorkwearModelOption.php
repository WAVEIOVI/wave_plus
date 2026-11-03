<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configurable option for a Workwear Model (e.g. "Size", "Color", "Fabric Weight").
 *
 * @property string $id
 * @property string $workwear_model_id
 * @property string $name
 * @property int $sort_order
 * @property string $ui_control
 */
class WorkwearModelOption extends Model
{
    use HasUlids;

    protected $table = 'workwear_model_options';

    protected $fillable = [
        'workwear_model_id',
        'name',
        'sort_order',
        'ui_control',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function workwearModel(): BelongsTo
    {
        return $this->belongsTo(WorkwearModel::class, 'workwear_model_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(WorkwearModelOptionValue::class, 'option_id')
            ->orderBy('sort_order');
    }
}
