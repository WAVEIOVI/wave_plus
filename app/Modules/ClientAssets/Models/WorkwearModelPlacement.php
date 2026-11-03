<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customization placement (logo, text, reflective band, badge...) on one
 * view (front/back) of a workwear model's garment illustration.
 *
 * @property string $id
 * @property string $workwear_model_id
 * @property string $view
 * @property string $type
 * @property string $label
 * @property string $zone
 * @property string|null $text
 * @property string|null $method
 * @property string|null $color
 * @property float|null $width_mm
 * @property float|null $height_mm
 * @property float $offset_x_mm
 * @property float $offset_y_mm
 * @property string|null $media_id
 * @property string|null $notes
 * @property int $sort_order
 */
class WorkwearModelPlacement extends Model
{
    use HasUlids;

    protected $table = 'workwear_model_placements';

    protected $fillable = [
        'workwear_model_id', 'view', 'type', 'label', 'zone', 'text', 'method', 'color',
        'width_mm', 'height_mm', 'offset_x_mm', 'offset_y_mm', 'media_id', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'width_mm' => 'float',
            'height_mm' => 'float',
            'offset_x_mm' => 'float',
            'offset_y_mm' => 'float',
            'sort_order' => 'integer',
        ];
    }

    public function workwearModel(): BelongsTo
    {
        return $this->belongsTo(WorkwearModel::class, 'workwear_model_id');
    }
}
