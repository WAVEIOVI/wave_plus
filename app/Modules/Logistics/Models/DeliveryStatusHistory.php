<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Models\User;
use App\Modules\Logistics\Enums\DwoStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryStatusHistory extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'delivery_status_histories';

    protected $fillable = [
        'dwo_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'from_status' => DwoStatus::class,
        'to_status' => DwoStatus::class,
        'changed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function deliveryWorkOrder()
    {
        return $this->belongsTo(DeliveryWorkOrder::class, 'dwo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
