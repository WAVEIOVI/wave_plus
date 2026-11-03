<?php

namespace App\Modules\Logistics\Events;

use App\Modules\Logistics\Models\DeliveryWorkOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DWOStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $dwo;

    public $oldStatus;

    public $newStatus;

    public $actor;

    /**
     * Create a new event instance.
     */
    public function __construct(DeliveryWorkOrder $dwo, string $oldStatus, string $newStatus, $actor)
    {
        $this->dwo = $dwo;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->actor = $actor;
    }
}
