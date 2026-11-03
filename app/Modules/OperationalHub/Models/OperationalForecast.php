<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\ClientAssets\Models\ClientSite;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperationalForecast extends Model
{
    use HasFactory, SoftDeletes;
    use HasUlids;

    protected $table = 'operational_forecasts';

    protected $fillable = [
        'title',
        'client_id',
        'equipment_id',
        'service_site_id',
        'forecast_type',
        'expected_date',
        'estimated_hours',
        'estimated_technicians',
        'priority',
        'notes',
        'status',
        'converted_swo_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'expected_date' => 'date',
        'estimated_hours' => 'decimal:2',
        'estimated_technicians' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClientEquipment::class, 'equipment_id');
    }

    public function serviceSite(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'service_site_id');
    }

    public function convertedSwo(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkOrder::class, 'converted_swo_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
