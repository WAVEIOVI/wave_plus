<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property string $guideline_id
 * @property int $sort_order
 * @property array $instruction
 * @property string $step_type
 * @property bool $is_required
 * @property string|null $expected_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GuidelineStep extends Model
{
    use HasTranslations;
    use HasUlids;

    protected $table = 'guideline_steps';

    public array $translatable = ['instruction'];

    protected $fillable = [
        'guideline_id',
        'sort_order',
        'instruction',
        'step_type',
        'is_required',
        'expected_value',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function guideline(): BelongsTo
    {
        return $this->belongsTo(Guideline::class, 'guideline_id');
    }
}
