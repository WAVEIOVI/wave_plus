<?php

declare(strict_types=1);

namespace App\Events;

use App\Modules\Crm\Models\SalesOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalesOrderAccepted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SalesOrder $salesOrder,
        public ?string $actorId = null
    ) {}
}
