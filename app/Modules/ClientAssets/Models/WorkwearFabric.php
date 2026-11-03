<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $composition
 * @property int|null $weight_gsm
 * @property string|null $weave_type
 * @property array|null $properties
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 */
class WorkwearFabric extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'workwear_fabrics';

    protected $fillable = [
        'code',
        'name',
        'composition',
        'weight_gsm',
        'weave_type',
        'properties',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weight_gsm' => 'integer',
            'properties' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function workwearModels(): HasMany
    {
        return $this->hasMany(WorkwearModel::class, 'fabric_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
