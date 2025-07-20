<?php

namespace App\Events;

use App\Models\DropshipOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DropshipOrderCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DropshipOrder $dropshipOrder;

    public function __construct(DropshipOrder $dropshipOrder)
    {
        $this->dropshipOrder = $dropshipOrder;
    }
}
