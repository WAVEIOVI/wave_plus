<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\Hr\Models\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopExitPass extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'workshop_exit_passes';

    protected $fillable = [
        'exit_pass_number',
        'swo_id',
        'mission_id',
        'destination_workshop_unit_id',
        'status',
        'transfer_date',
        'reason',
        'notes',
        'responsible_employee_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public function serviceWorkOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkOrder::class, 'swo_id');
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    public function destinationWorkshopUnit(): BelongsTo
    {
        return $this->belongsTo(WorkshopUnit::class, 'destination_workshop_unit_id');
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function exitPassEquipment(): HasMany
    {
        return $this->hasMany(WorkshopExitPassEquipment::class, 'exit_pass_id');
    }

    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(
            ClientEquipment::class,
            'workshop_exit_pass_equipment',
            'exit_pass_id',
            'equipment_id'
        )->withPivot(['id', 'condition', 'notes'])->withTimestamps();
    }
}
