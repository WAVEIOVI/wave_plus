<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\ClientAssets\Models\ClientSite;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $sales_order_id
 * @property string $bp_address_id
 * @property string|null $client_site_id
 * @property string $purpose
 * @property string|null $scope_type
 * @property string|null $equipment_scope
 * @property array|null $equipment_ids
 * @property bool $is_primary
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read SalesOrder $salesOrder
 * @property-read BpAddress $bpAddress
 * @property-read ClientSite|null $clientSite
 */
class SalesOrderLocation extends Model
{
    use HasUlids;

    protected $table = 'sales_order_locations';

    protected $fillable = [
        'sales_order_id',
        'bp_address_id',
        'client_site_id',
        'purpose',
        'scope_type',
        'equipment_scope',
        'equipment_ids',
        'is_primary',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'metadata' => 'array',
            'equipment_ids' => 'array',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function bpAddress(): BelongsTo
    {
        return $this->belongsTo(BpAddress::class, 'bp_address_id');
    }

    public function clientSite(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'client_site_id');
    }
}
