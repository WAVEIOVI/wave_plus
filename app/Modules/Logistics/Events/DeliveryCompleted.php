<?php

namespace App\Modules\Logistics\Events;

use App\Modules\Logistics\Models\DeliveryWorkOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $dwo;

    /**
     * Create a new event instance.
     */
    public function __construct(DeliveryWorkOrder $dwo)
    {
        $this->dwo = $dwo;
    }
}
