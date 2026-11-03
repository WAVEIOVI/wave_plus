<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\WorkTeam;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\OperationalHub\Enums\MissionMode;
use App\Modules\OperationalHub\Enums\MissionStatus;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mission extends Model
{
    use GeneratesReference, HasFactory, SoftDeletes;
    use HasUlids;

    protected $table = 'missions';

    protected $fillable = [
        'mission_number',
        'swo_id',
        'mode',
        'status',
        'workshop_unit_id',
        'service_site_id',
        'team_id',
        'lead_technician_id',
        'vehicle_id',
        'planned_date',
        'planned_start',
        'planned_end',
        'planned_start_time',
        'planned_duration_hours',
        'actual_start_at',
        'actual_end_at',
        'preparation_notes',
        'client_contact_name',
        'client_reception_at',
        'client_signature',
        'client_satisfaction',
        'completion_notes',
        'cancellation_reason',
        'source_field_mission_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => MissionStatus::class,
        'mode' => MissionMode::class,
        'planned_date' => 'date',
        'planned_start' => 'datetime',
        'planned_end' => 'datetime',
        'planned_duration_hours' => 'decimal:2',
        'actual_start_at' => 'datetime',
        'actual_end_at' => 'datetime',
        'client_reception_at' => 'datetime',
    ];

    protected function getReferenceColumn(): string
    {
        return 'mission_number';
    }

    protected function getDocumentSequenceName(): string
    {
        return 'MSN-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function swo(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkOrder::class, 'swo_id');
    }

    public function workshopUnit(): BelongsTo
    {
        return $this->belongsTo(WorkshopUnit::class, 'workshop_unit_id');
    }

    public function serviceSite(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'service_site_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(WorkTeam::class, 'team_id');
    }

    public function leadTechnician(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'lead_technician_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MissionAttendance::class, 'mission_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(MissionChecklist::class, 'mission_id');
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class, 'mission_id');
    }

    public function findings(): MorphMany
    {
        return $this->morphMany(Finding::class, 'findable');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(MissionStatusHistory::class, 'mission_id')->orderByDesc('changed_at');
    }

    public function sourceFieldMission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'source_field_mission_id');
    }

    public function derivedMissions(): HasMany
    {
        return $this->hasMany(Mission::class, 'source_field_mission_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function missionEmployees(): HasMany
    {
        return $this->hasMany(MissionEmployee::class, 'mission_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'mission_employees', 'mission_id', 'employee_id')
            ->withPivot('is_lead')
            ->withTimestamps();
    }

    public function exitPasses(): HasMany
    {
        return $this->hasMany(WorkshopExitPass::class, 'mission_id');
    }

    public function serviceDeliveryNotes(): HasMany
    {
        return $this->hasMany(ServiceDeliveryNote::class, 'mission_id');
    }
}
