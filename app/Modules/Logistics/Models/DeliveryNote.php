<?php

namespace App\Modules\Logistics\Models;

use App\Modules\BusinessPartners\Models\Client;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryNote extends Model
{
    use HasFactory, SoftDeletes;
    use HasUlids;

    protected $fillable = [
        'dn_number', 'dwo_id', 'client_id', 'delivery_date', 'status',
        'recipient_name', 'recipient_title', 'signature_data',
        'signature_at', 'stock_posted_at', 'stock_posted_by',
        'condition_overall', 'pdf_path',
        'ged_document_id', 'client_remarks', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'signature_at' => 'datetime',
        'stock_posted_at' => 'datetime',
    ];

    public function deliveryWorkOrder()
    {
        return $this->belongsTo(DeliveryWorkOrder::class, 'dwo_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
