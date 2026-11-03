<?php

namespace App\Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnOrderItem extends Model
{
    use HasFactory, SoftDeletes;
    use HasUlids;

    protected $fillable = [
        'return_order_id', 'dwo_line_id', 'qty_returned',
        'condition', 'stock_destination', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'qty_returned' => 'decimal:3',
    ];

    public function returnOrder()
    {
        return $this->belongsTo(ReturnOrder::class);
    }

    public function dwoItem()
    {
        return $this->belongsTo(DwoLine::class, 'dwo_line_id');
    }
}
