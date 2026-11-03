<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Logistics\Enums\DwoPriority;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryWorkOrder extends Model
{
    use GeneratesReference, HasFactory, SoftDeletes;
    use HasUlids;

    protected $table = 'delivery_work_orders';

    protected $fillable = [
        'dwo_number',
        'sales_order_id',
        'client_id',
        'warehouse_id',
        'delivery_site_id',
        'delivery_bp_address_id',
        'delivery_address_snapshot',
        'approved_driver_id',
        'vehicle_id',
        'status',
        'scheduled_date',
        'planned_date',
        'wave_id',
        'dispatched_at',
        'delivered_at',
        'closed_at',
        'exception_at',
        'eta',
        'priority',
        'fulfillment_method',
        'delivery_service_provider_id',
        'carrier_name',
        'estimated_delivery_cost',
        'actual_delivery_cost',
        'notes',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => DwoStatus::class,
        'priority' => DwoPriority::class,
        'delivery_address_snapshot' => 'array',
        'scheduled_date' => 'date',
        'planned_date' => 'date',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'closed_at' => 'datetime',
        'exception_at' => 'datetime',
        'eta' => 'datetime',
        'approved_at' => 'datetime',
        'estimated_delivery_cost' => 'decimal:3',
        'actual_delivery_cost' => 'decimal:3',
    ];

    protected function getReferenceColumn(): string
    {
        return 'dwo_number';
    }

    protected function getDocumentSequenceName(): string
    {
        return 'DWO-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(DwoLine::class, 'dwo_id');
    }

    /**
     * Backward-compatibility alias for lines().
     */
    public function items(): HasMany
    {
        return $this->hasMany(DwoLine::class, 'dwo_id');
    }

    public function trackings(): HasMany
    {
        return $this->hasMany(ShipmentTracking::class, 'dwo_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnOrder::class, 'dwo_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseSite::class, 'warehouse_id');
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(BpAddress::class, 'delivery_bp_address_id');
    }

    public function deliverySite(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'delivery_site_id');
    }

    public function wave(): BelongsTo
    {
        return $this->belongsTo(DeliveryWave::class, 'wave_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function approvedDriver(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Hr\Models\ApprovedDriver::class, 'approved_driver_id');
    }

    public function driver(): BelongsTo
    {
        return $this->approvedDriver();
    }

    public function mobileWarehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Models\WarehouseSite::class, 'mobile_warehouse_id');
    }

    public function deliveryServiceProvider(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Logistics\Models\DeliveryServiceProvider::class, 'delivery_service_provider_id');
    }

    public function pickingList(): HasOne
    {
        return $this->hasOne(PickingList::class, 'dwo_id');
    }

    public function deliveryNote(): HasOne
    {
        return $this->hasOne(DeliveryNote::class, 'dwo_id');
    }

    public function pod(): HasOne
    {
        return $this->hasOne(ProofOfDelivery::class, 'dwo_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(DeliveryException::class, 'dwo_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(DeliveryStatusHistory::class, 'dwo_id')->orderBy('created_at');
    }

    public function reservations(): MorphMany
    {
        return $this->morphMany(StockReservation::class, 'reservable');
    }

    // ── Helper Domain Methods ────────────────────────────────────────────────

    public function isPickingComplete(): bool
    {
        if ($this->lines->isEmpty()) {
            return false;
        }

        return $this->lines->every(function (DwoLine $line) {
            return (float) $line->qty_picked >= (float) $line->qty_allocated;
        });
    }

    public function isDispatchable(): bool
    {
        if ($this->status !== DwoStatus::Loaded) {
            return false;
        }

        return $this->lines->every(function (DwoLine $line) {
            return (float) $line->qty_loaded >= (float) $line->qty_allocated;
        });
    }

    public function isClosable(): bool
    {
        return in_array($this->status, [
            DwoStatus::Delivered,
            DwoStatus::PartiallyDelivered,
            DwoStatus::DeliveryFailed,
        ], true);
    }
}
