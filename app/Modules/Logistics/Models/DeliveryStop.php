<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\Logistics\Enums\DeliveryStopStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryStop extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'delivery_stops';

    protected $fillable = [
        'wave_id',
        'dwo_id',
        'sequence',
        'delivery_bp_address_id',
        'planned_arrival',
        'actual_arrival',
        'status',
        'notes',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'planned_arrival' => 'datetime',
        'actual_arrival' => 'datetime',
        'status' => DeliveryStopStatus::class,
    ];

    public function wave()
    {
        return $this->belongsTo(DeliveryWave::class, 'wave_id');
    }

    public function deliveryWorkOrder()
    {
        return $this->belongsTo(DeliveryWorkOrder::class, 'dwo_id');
    }

    public function deliveryAddress()
    {
        return $this->belongsTo(BpAddress::class, 'delivery_bp_address_id');
    }
}
