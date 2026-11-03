<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\OperationalHub\Enums\WorkshopReceptionStatus;
use App\Modules\OperationalHub\Enums\WorkshopUnitStatus;
use App\Modules\OperationalHub\Enums\WorkshopUnitType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class WorkshopUnit extends Model
{
    use HasFactory, HasTranslations, HasUlids, SoftDeletes;

    protected $table = 'workshop_units';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'status',
        'location',
        'vehicle_id',
        'capacity',
        'capacity_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type'     => WorkshopUnitType::class,
        'status'   => WorkshopUnitStatus::class,
        'capacity' => 'integer',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function swos(): HasMany
    {
        return $this->hasMany(ServiceWorkOrder::class, 'workshop_unit_id');
    }

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class, 'workshop_unit_id');
    }

    public function receptions(): HasMany
    {
        return $this->hasMany(WorkshopReception::class, 'workshop_unit_id');
    }

    /**
     * Receptions still occupying the unit (incoming / diagnosis / repair).
     */
    public function activeReceptions(): HasMany
    {
        return $this->hasMany(WorkshopReception::class, 'workshop_unit_id')
            ->whereIn(
                'status',
                array_map(static fn (WorkshopReceptionStatus $s) => $s->value, WorkshopReceptionStatus::activeStatuses()),
            );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
