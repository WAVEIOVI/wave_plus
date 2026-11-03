<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Modules\ClientAssets\Models\ClientEquipment;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDeliveryNoteLine extends Model
{
    use HasUlids;

    protected $table = 'service_delivery_note_lines';

    protected $fillable = [
        'sdn_id',
        'intervention_id',
        'equipment_id',
        'description',
        'service_type',
        'result',
        'technician_name',
        'documented_at',
    ];

    protected $casts = [
        'documented_at' => 'datetime',
    ];

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(ServiceDeliveryNote::class, 'sdn_id');
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class, 'intervention_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClientEquipment::class, 'equipment_id');
    }
}
