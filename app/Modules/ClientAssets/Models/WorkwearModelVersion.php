<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $workwear_model_id
 * @property int $version_number
 * @property array $snapshot
 * @property string|null $change_summary
 * @property int|null $created_by
 */
class WorkwearModelVersion extends Model
{
    use HasUlids;

    protected $table = 'workwear_model_versions';

    protected $fillable = [
        'workwear_model_id',
        'version_number',
        'snapshot',
        'change_summary',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function workwearModel(): BelongsTo
    {
        return $this->belongsTo(WorkwearModel::class, 'workwear_model_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
