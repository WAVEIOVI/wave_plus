<?php

namespace App\Modules\Logistics\Events;

use App\Modules\Logistics\Models\ReturnOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnValidated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $returnOrder;

    public $actor;

    /**
     * Create a new event instance.
     */
    public function __construct(ReturnOrder $returnOrder, $actor)
    {
        $this->returnOrder = $returnOrder;
        $this->actor = $actor;
    }
}
