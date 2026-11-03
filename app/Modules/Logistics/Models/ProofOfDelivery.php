<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Models\User;
use App\Modules\Logistics\Enums\DeliveryCondition;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProofOfDelivery extends Model
{
    use HasFactory, SoftDeletes;
    use HasUlids;

    protected $table = 'proof_of_deliveries';

    protected $fillable = [
        'dwo_id',
        'delivered_at',
        'receiver_name',
        'receiver_title',
        'signature_data',
        'signed_at',
        'condition_overall',
        'notes',
        'latitude',
        'longitude',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'signed_at' => 'datetime',
        'condition_overall' => DeliveryCondition::class,
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function deliveryWorkOrder()
    {
        return $this->belongsTo(DeliveryWorkOrder::class, 'dwo_id');
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
