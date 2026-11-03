<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryWaveItem extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'delivery_wave_items';

    protected $fillable = [
        'wave_id',
        'dwo_id',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function wave()
    {
        return $this->belongsTo(DeliveryWave::class, 'wave_id');
    }

    public function deliveryWorkOrder()
    {
        return $this->belongsTo(DeliveryWorkOrder::class, 'dwo_id');
    }
}
