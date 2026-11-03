<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $blueprint_id
 * @property string $intervention_type
 * @property int|null $first_interval_months
 * @property int|null $recurring_interval_months
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Modules\Catalog\Models\EquipmentBlueprint|null $blueprint
 * @property-read \App\Modules\Catalog\Models\Guideline|null $guideline
 */
class BlueprintInterventionCycle extends Model
{
    use HasUlids;

    protected $table = 'blueprint_intervention_cycles';

    protected $fillable = [
        'blueprint_id',
        'intervention_type',
        'first_interval_months',
        'recurring_interval_months',
        'notes',
    ];

    // ─── Relationships ───────────────────────────────────────────

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(EquipmentBlueprint::class, 'blueprint_id');
    }
}
