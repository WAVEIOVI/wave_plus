<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Modules\Catalog\Models\BlueprintOption;
use App\Modules\Catalog\Models\BlueprintOptionValue;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $equipment_id
 * @property string $blueprint_option_id
 * @property string|null $option_value_id
 * @property string|null $option_name
 * @property string|null $value_text
 * @property float|null $value_number
 * @property bool|null $value_boolean
 * @property \Illuminate\Support\Carbon|null $value_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Modules\ClientAssets\Models\ClientEquipment|null $equipment
 * @property-read \App\Modules\Catalog\Models\BlueprintOption|null $blueprintOption
 * @property-read \App\Modules\Catalog\Models\BlueprintOptionValue|null $blueprintOptionValue
 */
class EquipmentOptionValue extends Model
{
    use HasUlids;

    protected $table = 'equipment_option_values';

    protected $fillable = [
        'equipment_id',
        'blueprint_option_id',
        'option_value_id',
        'option_name',
        'value_text',
        'value_number',
        'value_boolean',
        'value_date',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:4',
            'value_boolean' => 'boolean',
            'value_date' => 'date',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClientEquipment::class, 'equipment_id');
    }

    public function blueprintOption(): BelongsTo
    {
        return $this->belongsTo(BlueprintOption::class, 'blueprint_option_id');
    }

    public function blueprintOptionValue(): BelongsTo
    {
        return $this->belongsTo(BlueprintOptionValue::class, 'option_value_id');
    }

    /**
     * Formatted string value of the snapshot.
     */
    public function getDisplayValueAttribute(): string
    {
        if ($this->value_text !== null) {
            return (string) $this->value_text;
        }
        if ($this->value_number !== null) {
            return (string) $this->value_number;
        }
        if ($this->value_boolean !== null) {
            return $this->value_boolean ? 'Yes' : 'No';
        }
        if ($this->value_date !== null) {
            return $this->value_date->toDateString();
        }
        if ($this->blueprintOptionValue) {
            return (string) $this->blueprintOptionValue->getTranslation('value', 'en');
        }

        return '—';
    }
}
