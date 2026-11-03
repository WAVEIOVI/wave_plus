<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\ClientAssets\Models\ClientEquipment;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Intervention extends Model
{
    use HasFactory, SoftDeletes;
    use HasUlids;

    protected $table = 'interventions';

    protected $fillable = [
        'mission_id',
        'equipment_id',
        'blueprint_id',
        'service_type',
        'status',
        'result',
        'technician_notes',
        'technical_observations',
        'measurements',
        'started_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'measurements' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClientEquipment::class, 'equipment_id');
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(EquipmentBlueprint::class, 'blueprint_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(InterventionMaterial::class, 'intervention_id');
    }

    public function findings(): MorphMany
    {
        return $this->morphMany(Finding::class, 'findable');
    }

    public function deliveryNoteLines(): HasMany
    {
        return $this->hasMany(ServiceDeliveryNoteLine::class, 'intervention_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
