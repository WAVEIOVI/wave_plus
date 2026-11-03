<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Models;

use App\Models\User;
use App\Modules\Logistics\Enums\DeliveryExceptionReason;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryException extends Model
{
    use HasFactory, SoftDeletes;
    use HasUlids;

    protected $table = 'delivery_exceptions';

    protected $fillable = [
        'dwo_id',
        'dwo_line_id',
        'reason',
        'affected_qty',
        'notes',
        'resolution_notes',
        'status',
        'resolved_at',
        'resolved_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'reason' => DeliveryExceptionReason::class,
        'affected_qty' => 'decimal:3',
        'resolved_at' => 'datetime',
    ];

    public function deliveryWorkOrder()
    {
        return $this->belongsTo(DeliveryWorkOrder::class, 'dwo_id');
    }

    public function line()
    {
        return $this->belongsTo(DwoLine::class, 'dwo_line_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
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
