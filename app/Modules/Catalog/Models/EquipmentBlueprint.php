<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property string $code
 * @property array $name
 * @property array|null $description
 * @property string $status
 * @property string|null $equipment_class_id
 * @property float|null $working_pressure_bar
 * @property string|null $fire_class_rating
 * @property int|null $shelf_life_years
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Modules\Catalog\Models\EquipmentClass|null $equipmentClass
 */
class EquipmentBlueprint extends Model
{
    use HasTranslations, HasUlids, SoftDeletes;

    protected $table = 'equipment_blueprints';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'equipment_class_id',
        'working_pressure_bar',
        'fire_class_rating',
        'shelf_life_years',
    ];

    protected function casts(): array
    {
        return [
            'working_pressure_bar' => 'decimal:2',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function equipmentClass(): BelongsTo
    {
        return $this->belongsTo(EquipmentClass::class, 'equipment_class_id');
    }

    /**
     * Guidelines attached via the M2M pivot (many guidelines per blueprint).
     */
    public function guidelines(): BelongsToMany
    {
        return $this->belongsToMany(
            Guideline::class,
            'blueprint_guideline',
            'equipment_blueprint_id',
            'guideline_id',
        )->using(BlueprintGuideline::class)->withTimestamps();
    }

    /**
     * Blueprint options (e.g. Capacity, Pressure Type).
     */
    public function blueprintOptions(): HasMany
    {
        return $this->hasMany(BlueprintOption::class, 'blueprint_id')->orderBy('sort_order');
    }

    /**
     * Intervention cycles (inspection, refill, hydro_test schedules).
     */
    public function interventionCycles(): HasMany
    {
        return $this->hasMany(BlueprintInterventionCycle::class, 'blueprint_id');
    }

    /**
     * Level-1 servicing items attached to this blueprint.
     * Each servicing row can have nested consumables via ->consumables().
     */
    public function servicingItems(): HasMany
    {
        return $this->hasMany(BlueprintServicing::class, 'blueprint_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to only active blueprints.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
