<?php

namespace App\Modules\Logistics\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnOrder extends Model
{
    use HasFactory, SoftDeletes;
    use HasUlids;

    protected $fillable = [
        'return_number', 'dwo_id', 'client_id', 'return_reason',
        'status', 'credit_note_id', 'stock_movement_id',
        'validated_by', 'validated_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(ReturnOrderItem::class);
    }

    public function deliveryWorkOrder()
    {
        return $this->belongsTo(DeliveryWorkOrder::class, 'dwo_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
