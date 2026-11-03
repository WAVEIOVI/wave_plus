<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Modules\ClientAssets\Models\ClientSite;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwoLocation extends Model
{
    use HasUlids;

    protected $table = 'swo_locations';

    protected $fillable = [
        'swo_id',
        'client_site_id',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function serviceWorkOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkOrder::class, 'swo_id');
    }

    public function clientSite(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'client_site_id');
    }
}
