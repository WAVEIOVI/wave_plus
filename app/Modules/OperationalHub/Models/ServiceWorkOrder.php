<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\Crm\Models\LsaDocument;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\WorkTeam;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\OperationalHub\Enums\SwoFulfillmentMode;
use App\Modules\OperationalHub\Enums\SwoPriority;
use App\Modules\OperationalHub\Enums\SwoStatus;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceWorkOrder extends Model
{
    use GeneratesReference, HasFactory, SoftDeletes;
    use HasUlids;

    protected $table = 'service_work_orders';

    protected $fillable = [
        'swo_number',
        'sales_order_id',
        'fulfillment_mode',
        'lsa_id',
        'client_id',
        'service_site_id',
        'assigned_team_id',
        'team_id',
        'lead_technician_id',
        'workshop_unit_id',
        'vehicle_id',
        'approved_driver_id',
        'mobile_warehouse_id',
        'status',
        'scheduled_date',
        'planned_date',
        'planned_start',
        'planned_end',
        'planned_start_time',
        'planned_duration_hours',
        'started_at',
        'completed_at',
        'closed_at',
        'priority',
        'notes',
        'checklist_template_id',
        'cancellation_reason',
        'source_swo_id',
        'source_mission_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => SwoStatus::class,
        'priority' => SwoPriority::class,
        'fulfillment_mode' => SwoFulfillmentMode::class,
        'scheduled_date' => 'date',
        'planned_date' => 'date',
        'planned_start' => 'datetime',
        'planned_end' => 'datetime',
        'planned_duration_hours' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected function getReferenceColumn(): string
    {
        return 'swo_number';
    }

    protected function getDocumentSequenceName(): string
    {
        return 'SWO-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    // ─── Relationships ───────────────────────────────────────────

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function lsaDocument(): BelongsTo
    {
        return $this->belongsTo(LsaDocument::class, 'lsa_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function serviceSite(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'service_site_id');
    }

    public function assignedTeam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_team_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(WorkTeam::class, 'team_id');
    }

    public function leadTechnician(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'lead_technician_id');
    }

    public function workshopUnit(): BelongsTo
    {
        return $this->belongsTo(WorkshopUnit::class, 'workshop_unit_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function approvedDriver(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Hr\Models\ApprovedDriver::class, 'approved_driver_id');
    }

    public function mobileWarehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\WarehouseSite::class, 'mobile_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SwoItem::class, 'swo_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SwoItem::class, 'swo_id');
    }

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class, 'swo_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(SwoStatusHistory::class, 'swo_id')->orderByDesc('changed_at');
    }

    public function sourceSwo(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkOrder::class, 'source_swo_id');
    }

    public function workshopSwos(): HasMany
    {
        return $this->hasMany(ServiceWorkOrder::class, 'source_swo_id');
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(ServiceDeliveryNote::class, 'swo_id');
    }

    public function receptions(): HasMany
    {
        return $this->hasMany(WorkshopReception::class, 'swo_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(SwoLocation::class, 'swo_id')->orderBy('sort_order');
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(ClientSite::class, 'swo_locations', 'swo_id', 'client_site_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function swoEmployees(): HasMany
    {
        return $this->hasMany(SwoEmployee::class, 'swo_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'swo_employees', 'swo_id', 'employee_id')
            ->withPivot('is_lead')
            ->withTimestamps();
    }

    public function operationalCosts(): HasMany
    {
        return $this->hasMany(SwoOperationalCost::class, 'swo_id');
    }

    public function exitPasses(): HasMany
    {
        return $this->hasMany(WorkshopExitPass::class, 'swo_id');
    }

    public function checklistTemplate(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }
}
