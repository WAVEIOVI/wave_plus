<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Modules\Catalog\Models\BlueprintInterventionCycle;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $equipment_id
 * @property string|null $source_blueprint_cycle_id
 * @property string $cycle_type
 * @property int $interval_value
 * @property string $interval_unit
 * @property \Illuminate\Support\Carbon|null $last_intervention_date
 * @property \Illuminate\Support\Carbon|null $next_due_date
 * @property bool $enabled
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Modules\ClientAssets\Models\ClientEquipment|null $equipment
 * @property-read \App\Modules\Catalog\Models\BlueprintInterventionCycle|null $sourceBlueprintCycle
 */
class EquipmentInterventionCycle extends Model
{
    use HasUlids;

    protected $table = 'equipment_intervention_cycles';

    protected $fillable = [
        'equipment_id',
        'source_blueprint_cycle_id',
        'cycle_type',
        'interval_value',
        'interval_unit',
        'last_intervention_date',
        'next_due_date',
        'enabled',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'interval_value' => 'integer',
            'last_intervention_date' => 'date',
            'next_due_date' => 'date',
            'enabled' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClientEquipment::class, 'equipment_id');
    }

    public function sourceBlueprintCycle(): BelongsTo
    {
        return $this->belongsTo(BlueprintInterventionCycle::class, 'source_blueprint_cycle_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
