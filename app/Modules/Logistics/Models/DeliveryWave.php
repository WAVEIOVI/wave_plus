<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Models\User;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Logistics\Enums\DeliveryWaveStatus;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryWave extends Model
{
    use GeneratesReference, HasFactory, SoftDeletes;
    use HasUlids;

    protected $table = 'delivery_waves';

    protected $fillable = [
        'wave_number',
        'planned_date',
        'dispatch_time',
        'warehouse_id',
        'vehicle_id',
        'approved_driver_id',
        'route_notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'planned_date' => 'date',
        'status' => DeliveryWaveStatus::class,
    ];

    protected function getReferenceColumn(): string
    {
        return 'wave_number';
    }

    protected function getDocumentSequenceName(): string
    {
        return 'WAVE-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    public function warehouse()
    {
        return $this->belongsTo(WarehouseSite::class, 'warehouse_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function approvedDriver()
    {
        return $this->belongsTo(\App\Modules\Hr\Models\ApprovedDriver::class, 'approved_driver_id');
    }

    public function driver()
    {
        return $this->approvedDriver();
    }

    public function waveItems()
    {
        return $this->hasMany(DeliveryWaveItem::class, 'wave_id')->orderBy('sequence');
    }

    public function workOrders()
    {
        return $this->hasMany(DeliveryWorkOrder::class, 'wave_id');
    }

    public function stops()
    {
        return $this->hasMany(DeliveryStop::class, 'wave_id')->orderBy('sequence');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
